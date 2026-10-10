<?php

namespace Tests\Unit;

use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

class MusicModelsTest extends TestCase
{
    public function test_administrator_role_is_explicit(): void
    {
        $this->assertFalse((new User)->isAdministrator());
        $this->assertFalse((new User)->forceFill(['role' => 'user'])->isAdministrator());
        $this->assertTrue((new User)->forceFill(['role' => 'admin'])->isAdministrator());
    }

    public function test_track_without_cover_uses_default_artwork(): void
    {
        $track = new Track(['title' => 'Tanpa Sampul', 'artist' => 'Artis']);

        $this->assertSame(asset('covers/default.svg'), $track->cover_url);
        $this->assertNull($track->stream_url);
    }

    public function test_demo_track_uses_public_artwork_and_online_audio(): void
    {
        $track = new Track([
            'cover_path' => 'covers/golden.svg',
            'audio_url' => 'https://example.com/sample.mp3',
            'is_demo' => true,
        ]);

        $this->assertTrue($track->is_demo);
        $this->assertSame(asset('covers/golden.svg'), $track->cover_url);
        $this->assertSame('https://example.com/sample.mp3', $track->stream_url);
    }

    public function test_uploaded_track_uses_protected_media_routes(): void
    {
        $track = new Track([
            'cover_path' => 'covers/upload.png',
            'audio_path' => 'audio/upload.mp3',
            'audio_url' => 'https://example.com/old.mp3',
        ]);
        $track->id = 42;

        $this->assertSame(route('tracks.cover', 42), $track->cover_url);
        $this->assertSame(route('tracks.audio', 42), $track->stream_url);
    }

    public function test_playlist_and_track_have_bidirectional_many_to_many_relationships(): void
    {
        $playlistTracks = (new Playlist)->tracks();
        $trackPlaylists = (new Track)->playlists();

        $this->assertInstanceOf(BelongsToMany::class, $playlistTracks);
        $this->assertInstanceOf(BelongsToMany::class, $trackPlaylists);
        $this->assertSame('playlist_track', $playlistTracks->getTable());
        $this->assertSame('playlist_track', $trackPlaylists->getTable());
    }
}
