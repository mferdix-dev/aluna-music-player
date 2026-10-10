<?php

namespace Tests\Feature;

use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MusicTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_and_playlist_can_be_managed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $track = Track::create(['title' => 'Test Song', 'artist' => 'Artist', 'is_demo' => true]);

        $this->postJson("/tracks/{$track->id}/library")->assertOk()->assertJsonPath('saved', true);
        $this->assertDatabaseHas('user_library_tracks', ['user_id' => $user->id, 'track_id' => $track->id]);
        $this->postJson("/tracks/{$track->id}/library")->assertOk()->assertJsonPath('saved', false);
        $this->assertDatabaseMissing('user_library_tracks', ['user_id' => $user->id, 'track_id' => $track->id]);

        $id = $this->postJson('/playlists', ['name' => 'Perjalanan'])->assertOk()->json('id');
        $this->postJson("/playlists/{$id}/tracks/{$track->id}")->assertOk()->assertJsonPath('saved', true);
        $this->assertTrue(Playlist::findOrFail($id)->tracks()->whereKey($track->id)->exists());
        $this->postJson("/playlists/{$id}/tracks/{$track->id}")->assertOk()->assertJsonPath('saved', false);
    }

    public function test_audio_upload_and_stream(): void
    {
        Storage::fake('local');
        Http::fake(['itunes.apple.com/*' => Http::response(['results' => []])]);
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        $id = $this->postJson('/tracks', [
            'title' => 'Lagu Baru',
            'artist' => 'Penyanyi',
            'audio' => UploadedFile::fake()->create('lagu.mp3', 100, 'audio/mpeg'),
        ])->assertOk()->json('id');

        $track = Track::findOrFail($id);
        $this->assertNull($track->user_id);
        $this->assertTrue($track->is_public);
        $this->assertNull($track->cover_path);
        $this->assertSame(asset('covers/default.svg'), $track->cover_url);
        Storage::assertExists($track->audio_path);
        $this->app['auth']->logout();
        $this->get('/')->assertOk()->assertSee('Lagu Baru');
        $this->get("/tracks/{$id}/audio")->assertOk();
        $this->actingAs($user);
        $this->deleteJson("/tracks/{$id}")->assertOk();
        Storage::assertMissing($track->audio_path);
    }

    public function test_matching_song_automatically_downloads_its_cover(): void
    {
        Storage::fake('local');
        $fakeImage = UploadedFile::fake()->image('album.png', 50, 50);
        $image = file_get_contents($fakeImage->getRealPath());
        Http::fake([
            'itunes.apple.com/*' => Http::response(['results' => [
                ['trackName' => 'Different Song', 'artistName' => 'Wrong Artist', 'artworkUrl100' => 'https://is1-ssl.mzstatic.com/wrong100x100bb.jpg'],
                ['trackName' => 'My Song', 'artistName' => 'The Artist', 'artworkUrl100' => 'https://is1-ssl.mzstatic.com/album100x100bb.png'],
            ]]),
            'is1-ssl.mzstatic.com/*' => Http::response($image, 200, ['Content-Type' => 'image/png']),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $id = $this->actingAs($admin)->postJson('/tracks', [
            'title' => 'My Song', 'artist' => 'The Artist',
            'audio' => UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg'),
        ])->assertOk()->assertJsonPath('cover_source', 'internet')->json('id');

        $track = Track::findOrFail($id);
        $this->assertStringStartsWith('covers/', $track->cover_path);
        Storage::assertExists($track->cover_path);
        $this->app['auth']->logout();
        $this->get("/tracks/{$id}/cover")->assertOk();
        Http::assertSent(fn ($request) => str_contains($request->url(), '600x600bb'));
    }

    public function test_manually_uploaded_cover_takes_priority_over_online_lookup(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $admin = User::factory()->create(['role' => 'admin']);
        $id = $this->actingAs($admin)->postJson('/tracks', [
            'title' => 'Manual', 'artist' => 'Artis',
            'audio' => UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg'),
            'cover' => UploadedFile::fake()->image('manual.png'),
        ])->assertOk()->assertJsonPath('cover_source', 'upload')->json('id');

        Storage::assertExists(Track::findOrFail($id)->cover_path);
        Http::assertNothingSent();
    }

    public function test_upload_still_succeeds_if_cover_service_is_unavailable(): void
    {
        Storage::fake('local');
        Http::fake(['itunes.apple.com/*' => Http::response('Offline', 503)]);
        $admin = User::factory()->create(['role' => 'admin']);

        $id = $this->actingAs($admin)->postJson('/tracks', [
            'title' => 'Tidak Ada Sampul', 'artist' => 'Artis',
            'audio' => UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg'),
        ])->assertOk()->assertJsonPath('cover_source', 'default')->json('id');

        $this->assertNull(Track::findOrFail($id)->cover_path);
        Storage::assertExists(Track::findOrFail($id)->audio_path);
    }

    public function test_cloud_upload_can_be_finalized_only_by_the_administrator_who_requested_it(): void
    {
        config()->set('filesystems.music_disk', 's3');
        Storage::fake('s3');
        Http::fake(['itunes.apple.com/*' => Http::response(['results' => []])]);
        $owner = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $key = 'audio/pending/'.Str::uuid().'.mp3';
        Storage::disk('s3')->put($key, 'mock audio');
        Cache::put('music-upload:'.$key, $owner->id, now()->addMinutes(15));

        $this->actingAs($other)->postJson('/tracks', [
            'title' => 'Lagu Cloud', 'artist' => 'Artis', 'audio_key' => $key,
        ])->assertUnprocessable();

        $id = $this->actingAs($owner)->postJson('/tracks', [
            'title' => 'Lagu Cloud', 'artist' => 'Artis', 'audio_key' => $key,
        ])->assertOk()->json('id');

        $this->assertSame($key, Track::findOrFail($id)->audio_path);
        $this->assertNull(Cache::get('music-upload:'.$key));
        $this->postJson('/tracks', [
            'title' => 'Lagu Duplikat', 'artist' => 'Artis', 'audio_key' => $key,
        ])->assertUnprocessable();
    }

    public function test_stream_search_and_resolve(): void
    {
        Http::fake([
            'music.youtube.com/*' => Http::response([
                'contents' => [
                    'musicResponsiveListItemRenderer' => [
                        'playlistItemData' => ['videoId' => 'abc123xyz'],
                        'flexColumns' => [
                            [
                                'musicResponsiveListItemFlexColumnRenderer' => [
                                    'text' => ['runs' => [['text' => 'Streaming Track']]]
                                ]
                            ],
                            [
                                'musicResponsiveListItemFlexColumnRenderer' => [
                                    'text' => ['runs' => [['text' => 'Stream Artist'], ['text' => ' • '], ['text' => '3:30']]]
                                ]
                            ]
                        ]
                    ]
                ]
            ])
        ]);

        $response = $this->getJson('/stream/search?q=Streaming+Track');
        $response->assertOk()->assertJsonStructure(['tracks' => [['youtube_id', 'title', 'artist']]]);

        $resolve = $this->postJson('/stream/resolve', [
            'youtube_id' => 'abc123xyz',
            'title' => 'Streaming Track',
            'artist' => 'Stream Artist',
            'album' => 'Stream Album',
            'duration' => 210,
            'cover' => 'https://i.ytimg.com/vi/abc123xyz/hqdefault.jpg',
        ]);

        $resolve->assertOk()
            ->assertJsonPath('title', 'Streaming Track')
            ->assertJsonPath('youtube_id', 'abc123xyz');

        $this->assertDatabaseHas('tracks', ['youtube_id' => 'abc123xyz', 'title' => 'Streaming Track']);
    }

    public function test_stream_lyrics(): void
    {
        Http::fake([
            'lrclib.net/*' => Http::response([
                'id' => 1234,
                'trackName' => 'Streaming Track',
                'artistName' => 'Stream Artist',
                'syncedLyrics' => '[00:10.00] Hello world',
                'plainLyrics' => 'Hello world',
            ])
        ]);

        $response = $this->getJson('/stream/lyrics?title=Streaming+Track&artist=Stream+Artist');
        $response->assertOk()
            ->assertJsonPath('lyrics.plain_lyrics', 'Hello world')
            ->assertJsonPath('lyrics.synced_lyrics', '[00:10.00] Hello world');
    }
}
