@extends('account.layout')
@section('title', 'Masuk')
@section('content')
    <div class="form-kicker">SELAMAT DATANG KEMBALI</div>
    <h2>Masuk ke Aluna<span>.</span></h2>
    <p class="form-description">Masuk untuk menyimpan lagu dan melanjutkan playlist pribadimu.</p>
    <form method="POST" action="{{ route('login') }}" class="account-form">
        @csrf
        <label>Alamat email<input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" autocomplete="email" required autofocus></label>
        @error('email') <small class="input-error">{{ $message }}</small> @enderror
        <label>Kata sandi<input type="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required></label>
        @error('password') <small class="input-error">{{ $message }}</small> @enderror
        <label class="remember"><input type="checkbox" name="remember"> Ingat saya</label>
        <button type="submit" class="account-submit">Masuk <span>→</span></button>
    </form>
    <div class="account-divider">atau</div>
    <p class="account-switch">Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a></p>
    <a class="guest-link" href="{{ route('home') }}">Lanjut dengarkan tanpa akun →</a>
@endsection
