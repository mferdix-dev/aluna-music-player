@extends('account.layout')
@section('title', 'Daftar')
@section('content')
    <div class="form-kicker">MULAI PERJALANAN MUSIKMU</div>
    <h2>Buat akun Aluna<span>.</span></h2>
    <p class="form-description">Buat akun gratis untuk punya koleksi dan playlist sendiri.</p>
    <form method="POST" action="{{ route('register') }}" class="account-form">
        @csrf
        <label>Nama tampilan<input type="text" name="name" value="{{ old('name') }}" placeholder="Nama kamu" autocomplete="name" maxlength="100" required autofocus></label>
        @error('name') <small class="input-error">{{ $message }}</small> @enderror
        <label>Alamat email<input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" autocomplete="email" required></label>
        @error('email') <small class="input-error">{{ $message }}</small> @enderror
        <label>Kata sandi<input type="password" name="password" placeholder="Minimal 8 karakter" autocomplete="new-password" minlength="8" required></label>
        @error('password') <small class="input-error">{{ $message }}</small> @enderror
        <label>Ulangi kata sandi<input type="password" name="password_confirmation" placeholder="Ulangi kata sandi" autocomplete="new-password" required></label>
        <button type="submit" class="account-submit">Buat akun <span>→</span></button>
    </form>
    <p class="account-switch">Sudah punya akun? <a href="{{ route('login') }}">Masuk di sini</a></p>
    <a class="guest-link" href="{{ route('home') }}">Lanjut dengarkan tanpa akun →</a>
@endsection
