<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function loginForm()
    {
        return view('account.login');
    }

    public function registerForm()
    {
        return view('account.register');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function profile(Request $request)
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $oldPath = $user->avatar_path;
            $user->avatar_path = $request->file('avatar')->store('avatars', config('filesystems.music_disk'));
            $user->fill(['name' => $data['name'], 'email' => $data['email']])->save();
            if ($oldPath) {
                Storage::disk(config('filesystems.music_disk'))->delete($oldPath);
            }
        } else {
            $user->update(['name' => $data['name'], 'email' => $data['email']]);
        }

        return back()->with('status', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Kata sandi berhasil diubah.');
    }

    public function avatar(Request $request)
    {
        $path = $request->user()->avatar_path;
        $disk = Storage::disk(config('filesystems.music_disk'));
        abort_unless($path && $disk->exists($path), 404);

        if (config('filesystems.music_disk') !== 'local') {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(30)));
        }

        return response()->file($disk->path($path));
    }
}
