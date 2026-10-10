<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Models\Track;
use App\Services\CoverArtFinder;
use App\Services\InnerTubeService;
use App\Services\LyricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MusicController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tracks = Track::where(fn ($query) => $query->where('is_demo', true)->orWhere('is_public', true)
            ->when($user, fn ($query) => $query->orWhere('user_id', $user->id)))
            ->latest()->get()->map(fn (Track $track) => [
                'id' => $track->id,
                'title' => $track->title,
                'artist' => $track->artist,
                'album' => $track->album ?: 'Single',
                'duration' => $track->duration,
                'cover' => $track->cover_url,
                'audio' => $track->stream_url,
                'youtube_id' => $track->youtube_id,
                'demo' => $track->is_demo,
                'deletable' => $user?->isAdministrator() && $track->is_public && ! $track->is_demo,
            ]);

        $onlineTracks = Cache::remember('home_online_tracks_v2', now()->addHours(6), function () {
            try {
                $service = app(InnerTubeService::class);
                return $service->search('Top Hits Indonesia 2026', 20);
            } catch (\Throwable) {
                return [];
            }
        });

        return view('music.index', ['musicData' => [
            'tracks' => $tracks,
            'onlineTracks' => $onlineTracks,
            'libraryIds' => $user ? DB::table('user_library_tracks')->where('user_id', $user->id)->pluck('track_id') : [],
            'playlists' => $user ? Playlist::where('user_id', $user->id)->with('tracks:id')->get()->map(fn (Playlist $playlist) => [
                'id' => $playlist->id,
                'name' => $playlist->name,
                'track_ids' => $playlist->tracks->pluck('id'),
            ]) : [],
            'authenticated' => (bool) $user,
            'administrator' => $user?->isAdministrator() ?? false,
            'cloudUpload' => config('filesystems.music_disk') === 's3',
            'loginUrl' => route('login'),
        ]]);
    }

    public function uploadUrl(Request $request)
    {
        abort_unless(config('filesystems.music_disk') === 's3', 404);
        $data = $request->validate(['extension' => 'required|in:mp3,m4a,mp4,ogg,wav,flac,aac,webm']);
        $contentType = [
            'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4',
            'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'flac' => 'audio/flac',
            'aac' => 'audio/aac', 'webm' => 'audio/webm',
        ][$data['extension']];
        $path = 'audio/pending/'.Str::uuid().'.'.$data['extension'];
        $signed = Storage::disk('s3')->temporaryUploadUrl($path, now()->addMinutes(15), ['ContentType' => $contentType]);
        Cache::put('music-upload:'.$path, $request->user()->id, now()->addMinutes(15));

        return response()->json(['key' => $path, 'url' => $signed['url'], 'headers' => $signed['headers']]);
    }

    public function storeTrack(Request $request, CoverArtFinder $coverArtFinder)
    {
        $cloud = config('filesystems.music_disk') === 's3';
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'artist' => 'required|string|max:150',
            'album' => 'nullable|string|max:150',
            'audio' => $cloud ? 'prohibited' : 'required|file|mimes:mp3,m4a,mp4,ogg,wav,flac,aac,webm|max:20480',
            'audio_key' => $cloud ? ['required', 'string', 'regex:/^audio\/pending\/[a-f0-9-]{36}\.(mp3|m4a|mp4|ogg|wav|flac|aac|webm)$/'] : 'prohibited',
            'cover' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
        ]);

        $disk = Storage::disk(config('filesystems.music_disk'));
        if ($cloud) {
            $audioPath = $data['audio_key'];
            if (Cache::get('music-upload:'.$audioPath) !== $request->user()->id || ! $disk->exists($audioPath)) {
                throw ValidationException::withMessages(['audio' => 'Unggahan audio belum selesai atau sudah kedaluwarsa.']);
            }
            if ($disk->size($audioPath) > 20 * 1024 * 1024) {
                throw ValidationException::withMessages(['audio' => 'Ukuran audio maksimal 20 MB.']);
            }
        } else {
            $audioPath = $request->file('audio')->store('audio', config('filesystems.music_disk'));
        }
        $coverPath = null;

        try {
            if ($request->hasFile('cover')) {
                $coverPath = $request->file('cover')->store('covers', config('filesystems.music_disk'));
            } else {
                $coverPath = $coverArtFinder->find($data['title'], $data['artist']);
            }
            $track = Track::create([
                'title' => $data['title'],
                'artist' => $data['artist'],
                'album' => $data['album'] ?? null,
                'audio_path' => $audioPath,
                'cover_path' => $coverPath,
                'is_public' => true,
            ]);
            if ($cloud) {
                Cache::forget('music-upload:'.$audioPath);
            }
        } catch (\Throwable $error) {
            $disk->delete(array_filter([$audioPath, $coverPath]));
            throw $error;
        }

        return response()->json(['message' => 'Lagu berhasil ditambahkan.', 'id' => $track->id, 'cover_source' => $request->hasFile('cover') ? 'upload' : ($coverPath ? 'internet' : 'default')]);
    }

    public function destroyTrack(Request $request, Track $track)
    {
        abort_unless($track->is_public, 404);
        if ($track->is_demo) {
            throw ValidationException::withMessages(['track' => 'Lagu contoh tidak dapat dihapus.']);
        }
        $paths = array_filter([$track->audio_path, $track->cover_path]);
        $track->delete();
        Storage::disk(config('filesystems.music_disk'))->delete($paths);

        return response()->json(['message' => 'Lagu dihapus.']);
    }

    public function toggleLibrary(Request $request, Track $track)
    {
        $this->ensureVisible($track, $request);
        $library = DB::table('user_library_tracks')->where('user_id', $request->user()->id)->where('track_id', $track->id);
        $saved = $library->exists();
        if ($saved) {
            $library->delete();
        } else {
            DB::table('user_library_tracks')->insert(['user_id' => $request->user()->id, 'track_id' => $track->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['saved' => ! $saved]);
    }

    public function storePlaylist(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:80']);
        $playlist = Playlist::create([...$data, 'user_id' => $request->user()->id]);

        return response()->json(['id' => $playlist->id, 'name' => $playlist->name, 'track_ids' => []]);
    }

    public function togglePlaylistTrack(Request $request, Playlist $playlist, Track $track)
    {
        abort_unless($playlist->user_id === $request->user()->id, 404);
        $this->ensureVisible($track, $request);
        $hasTrack = $playlist->tracks()->whereKey($track->id)->exists();
        if ($hasTrack) {
            $playlist->tracks()->detach($track);
        } else {
            $playlist->tracks()->attach($track);
        }

        return response()->json(['saved' => ! $hasTrack]);
    }

    public function audio(Request $request, Track $track)
    {
        $this->ensureVisible($track, $request);
        $disk = Storage::disk(config('filesystems.music_disk'));
        abort_unless($track->audio_path && $disk->exists($track->audio_path), 404);

        if (config('filesystems.music_disk') !== 'local') {
            return redirect()->away($disk->temporaryUrl($track->audio_path, now()->addMinutes(30)));
        }

        return response()->file($disk->path($track->audio_path), [
            'Content-Type' => $disk->mimeType($track->audio_path) ?: 'audio/mpeg',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    public function cover(Request $request, Track $track)
    {
        $this->ensureVisible($track, $request);
        if ($track->cover_path && (str_starts_with($track->cover_path, 'http://') || str_starts_with($track->cover_path, 'https://'))) {
            return redirect()->away($track->cover_path);
        }
        $disk = Storage::disk(config('filesystems.music_disk'));
        abort_unless($track->cover_path && $disk->exists($track->cover_path), 404);

        if (config('filesystems.music_disk') !== 'local') {
            return redirect()->away($disk->temporaryUrl($track->cover_path, now()->addMinutes(30)));
        }

        return response()->file($disk->path($track->cover_path));
    }

    public function searchStream(Request $request, InnerTubeService $innerTube)
    {
        $query = (string) $request->query('q', '');
        if (trim($query) === '') {
            return response()->json(['tracks' => []]);
        }

        $results = $innerTube->search($query, 25);
        $videoIds = collect($results)->pluck('youtube_id')->filter()->all();
        $existing = Track::whereIn('youtube_id', $videoIds)->pluck('id', 'youtube_id');

        $tracks = array_map(function ($track) use ($existing) {
            $track['existing_id'] = $existing[$track['youtube_id']] ?? null;
            return $track;
        }, $results);

        return response()->json(['tracks' => $tracks]);
    }

    public function resolveStreamTrack(Request $request)
    {
        $data = $request->validate([
            'youtube_id' => 'required|string|max:32',
            'title' => 'required|string|max:200',
            'artist' => 'required|string|max:200',
            'album' => 'nullable|string|max:200',
            'duration' => 'nullable|integer|min:0',
            'cover' => 'nullable|string|max:500',
        ]);

        $track = Track::firstOrCreate(
            ['youtube_id' => $data['youtube_id']],
            [
                'title' => $data['title'],
                'artist' => $data['artist'],
                'album' => $data['album'] ?: 'Single',
                'duration' => $data['duration'] ?? 0,
                'cover_path' => $data['cover'] ?? null,
                'is_public' => true,
                'user_id' => $request->user()?->id,
            ]
        );

        return response()->json([
            'id' => $track->id,
            'title' => $track->title,
            'artist' => $track->artist,
            'album' => $track->album ?: 'Single',
            'duration' => $track->duration,
            'cover' => $track->cover_url,
            'audio' => $track->stream_url,
            'youtube_id' => $track->youtube_id,
            'demo' => false,
            'deletable' => false,
        ]);
    }

    public function lyrics(Request $request, LyricsService $lyricsService)
    {
        $title = (string) $request->query('title', '');
        $artist = (string) $request->query('artist', '');
        $duration = (int) $request->query('duration', 0);

        if (trim($title) === '' || trim($artist) === '') {
            return response()->json(['lyrics' => null]);
        }

        $lyrics = $lyricsService->getLyrics($title, $artist, $duration);

        return response()->json(['lyrics' => $lyrics]);
    }

    private function ensureVisible(Track $track, Request $request): void
    {
        abort_unless($track->is_demo || $track->is_public || ($request->user() && $track->user_id === $request->user()->id), 404);
    }
}
