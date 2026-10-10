<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('music:create-admin {email=admin@aluna.local}', function (string $email) {
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Alamat email tidak valid.');

        return 1;
    }

    if (User::where('email', $email)->exists()) {
        $this->error('Email sudah terdaftar. Gunakan email lain; perintah ini tidak menaikkan hak akses akun yang sudah ada.');

        return 1;
    }

    $password = Str::password(24);
    $user = User::create(['name' => 'Administrator', 'email' => $email, 'password' => $password]);
    $user->forceFill(['role' => 'admin'])->save();

    $this->info('Akun administrator berhasil dibuat. Simpan kata sandi sementara ini:');
    $this->line('Email: '.$email);
    $this->line('Kata sandi: '.$password);
    $this->warn('Ubah kata sandi setelah login melalui halaman Profil.');

    return 0;
})->purpose('Buat akun administrator baru dengan kata sandi acak');
