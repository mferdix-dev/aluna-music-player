<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\MusicController;
use App\Http\Middleware\EnsureAdministrator;
use Illuminate\Support\Facades\Route;

Route::get('/', [MusicController::class, 'index'])->name('home');
Route::get('/tracks/{track}/audio', [MusicController::class, 'audio'])->name('tracks.audio');
Route::get('/tracks/{track}/cover', [MusicController::class, 'cover'])->name('tracks.cover');
Route::get('/stream/search', [MusicController::class, 'searchStream'])->name('stream.search');
Route::post('/stream/resolve', [MusicController::class, 'resolveStreamTrack'])->name('stream.resolve');
Route::get('/stream/lyrics', [MusicController::class, 'lyrics'])->name('stream.lyrics');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AccountController::class, 'loginForm'])->name('login');
    Route::post('/login', [AccountController::class, 'login']);
    Route::get('/register', [AccountController::class, 'registerForm'])->name('register');
    Route::post('/register', [AccountController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::patch('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');
    Route::get('/profile/avatar', [AccountController::class, 'avatar'])->name('profile.avatar');
    Route::post('/tracks/{track}/library', [MusicController::class, 'toggleLibrary'])->name('tracks.library');
    Route::post('/playlists', [MusicController::class, 'storePlaylist'])->name('playlists.store');
    Route::post('/playlists/{playlist}/tracks/{track}', [MusicController::class, 'togglePlaylistTrack'])->name('playlists.tracks');
});

Route::middleware(['auth', EnsureAdministrator::class])->group(function () {
    Route::post('/tracks/upload-url', [MusicController::class, 'uploadUrl'])->name('tracks.upload-url');
    Route::post('/tracks', [MusicController::class, 'storeTrack'])->name('tracks.store');
    Route::delete('/tracks/{track}', [MusicController::class, 'destroyTrack'])->name('tracks.destroy');
});
