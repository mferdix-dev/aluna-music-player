<?php

namespace Tests\Feature;

use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_and_play_demo_but_personal_actions_require_login(): void
    {
        $demo = Track::create(['title' => 'Lagu Contoh', 'artist' => 'Artis', 'is_demo' => true, 'audio_url' => 'https://example.com/audio.mp3']);
        $private = Track::create(['title' => 'Privat', 'artist' => 'Pemilik', 'user_id' => User::factory()->create()->id]);

        $this->get('/')->assertOk()->assertSee('Lagu Contoh')->assertDontSee('Privat');
        $this->get('/login')->assertOk();
        $this->postJson("/tracks/{$demo->id}/library")->assertUnauthorized();
        $this->post('/playlists', ['name' => 'Pribadi'])->assertRedirect('/login');
        $this->get("/tracks/{$private->id}/audio")->assertNotFound();
    }

    public function test_registration_login_and_logout_manage_the_session(): void
    {
        $this->post('/register', [
            'name' => 'Nada', 'email' => 'nada@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'admin',
        ])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertSame('user', User::where('email', 'nada@example.com')->firstOrFail()->role);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => 'nada@example.com', 'password' => 'password123'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_invalid_login_and_registration_validation(): void
    {
        User::factory()->create(['email' => 'dipakai@example.com']);
        $this->post('/login', ['email' => 'dipakai@example.com', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->post('/register', ['name' => 'Baru', 'email' => 'dipakai@example.com', 'password' => '123', 'password_confirmation' => '123'])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_profile_can_be_updated_with_avatar_and_password(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['password' => 'password123']);
        $this->actingAs($user);
        $this->get('/profile')->assertOk()->assertSee($user->name);
        $this->patch('/profile', ['name' => 'Nama Baru', 'email' => 'baru@example.com', 'avatar' => UploadedFile::fake()->image('photo.png')])->assertRedirect();
        $this->assertSame('Nama Baru', $user->fresh()->name);
        Storage::assertExists($user->fresh()->avatar_path);
        $this->get('/profile/avatar')->assertOk();
        $this->patch('/profile/password', ['current_password' => 'salah', 'password' => 'password456', 'password_confirmation' => 'password456'])->assertSessionHasErrors('current_password');
        $this->patch('/profile/password', ['current_password' => 'password123', 'password' => 'password456', 'password_confirmation' => 'password456'])->assertRedirect();
        $this->assertTrue(Hash::check('password456', $user->fresh()->password));
    }

    public function test_users_cannot_access_each_others_library_playlists_or_uploaded_files(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $demo = Track::create(['title' => 'Umum', 'artist' => 'Artis', 'is_demo' => true]);
        Storage::put('audio/private.mp3', 'audio bytes');
        $private = Track::create(['title' => 'Rahasia', 'artist' => 'Artis', 'user_id' => $owner->id, 'audio_path' => 'audio/private.mp3']);
        $playlist = Playlist::create(['name' => 'Milikku', 'user_id' => $owner->id]);

        $this->actingAs($owner)->postJson("/tracks/{$demo->id}/library")->assertOk();
        $this->actingAs($other)->get('/')->assertOk()->assertDontSee('Rahasia')->assertDontSee('Milikku');
        $this->get("/tracks/{$private->id}/audio")->assertNotFound();
        $this->deleteJson("/tracks/{$private->id}")->assertForbidden();
        $this->postJson("/tracks/{$private->id}/library")->assertNotFound();
        $this->postJson("/playlists/{$playlist->id}/tracks/{$demo->id}")->assertNotFound();
        $this->assertDatabaseMissing('user_library_tracks', ['user_id' => $other->id, 'track_id' => $demo->id]);
    }

    public function test_only_administrator_sees_upload_button_and_can_upload(): void
    {
        $regular = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->get('/')->assertOk()->assertDontSee('id="upload-button"', false);
        $this->postJson('/tracks', [])->assertUnauthorized();
        $this->actingAs($regular)->get('/')->assertOk()->assertDontSee('id="upload-button"', false);
        $this->postJson('/tracks', [])->assertForbidden();
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('id="upload-button"', false);
        $this->postJson('/tracks', [])->assertUnprocessable();
    }

    public function test_public_admin_tracks_can_be_saved_by_regular_users_but_not_deleted(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $track = Track::create(['title' => 'Lagu Publik', 'artist' => 'Artis', 'is_public' => true]);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Lagu Publik');
        $this->postJson("/tracks/{$track->id}/library")->assertOk()->assertJsonPath('saved', true);
        $this->deleteJson("/tracks/{$track->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/tracks/{$track->id}")->assertOk();
        $this->assertDatabaseMissing('tracks', ['id' => $track->id]);
    }

    public function test_administrator_command_creates_new_account_without_promoting_existing_one(): void
    {
        $this->artisan('music:create-admin', ['email' => 'root@example.com'])->assertExitCode(0);
        $admin = User::where('email', 'root@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdministrator());
        $this->assertNotSame('', $admin->password);

        $regular = User::factory()->create(['email' => 'biasa@example.com']);
        $this->artisan('music:create-admin', ['email' => 'biasa@example.com'])->assertExitCode(1);
        $this->assertFalse($regular->fresh()->isAdministrator());
    }
}
