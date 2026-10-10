@extends('account.layout')
@section('title', 'Profil saya')
@section('content')
    <div class="form-kicker">AKUN PRIBADI</div>
    <h2>Profil saya<span>.</span></h2>
    <p class="form-description">Atur identitas yang menemani koleksi musikmu.</p>
    @if(session('status')) <div class="account-success">{{ session('status') }}</div> @endif
    <div class="profile-summary"><span class="profile-avatar">@if($user->avatar_path)<img src="{{ route('profile.avatar') }}" alt="Foto profil">@else{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}@endif</span><div><strong>{{ $user->name }}</strong><small>{{ $user->email }} @if($user->isAdministrator()) · Administrator @endif</small></div></div>
    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="account-form profile-form">
        @csrf @method('PATCH')
        <label>Nama tampilan<input type="text" name="name" value="{{ old('name', $user->name) }}" maxlength="100" required></label>
        @error('name') <small class="input-error">{{ $message }}</small> @enderror
        <label>Alamat email<input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
        @error('email') <small class="input-error">{{ $message }}</small> @enderror
        <label>Foto profil (opsional, maks. 2 MB)<input type="file" name="avatar" accept="image/png,image/jpeg,image/webp"></label>
        @error('avatar') <small class="input-error">{{ $message }}</small> @enderror
        <button type="submit" class="account-submit">Simpan perubahan <span>→</span></button>
    </form>
    <div class="profile-separator"></div>
    <h3>Ganti kata sandi</h3>
    <form method="POST" action="{{ route('profile.password') }}" class="account-form profile-form">
        @csrf @method('PATCH')
        <label>Kata sandi saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
        @error('current_password') <small class="input-error">{{ $message }}</small> @enderror
        <label>Kata sandi baru<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
        @error('password') <small class="input-error">{{ $message }}</small> @enderror
        <label>Ulangi kata sandi baru<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
        <button type="submit" class="account-submit">Perbarui kata sandi <span>→</span></button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="profile-logout">@csrf<button type="submit">Keluar dari akun</button></form>
@endsection
