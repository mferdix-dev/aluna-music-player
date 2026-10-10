<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Aluna</title>
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/music.css') }}">
    <link rel="stylesheet" href="{{ asset('css/account.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dark.css') }}">
</head>
<body class="account-page">
    <div class="account-shell">
        <aside class="account-hero">
            <a href="{{ route('home') }}" class="account-brand"><span class="account-mark">♫</span> aluna<span>.</span></a>
            <div class="hero-content"><span class="hero-kicker">YOUR SPACE, YOUR SOUND</span><h1>Musik terasa<br>lebih dekat <em>di sini.</em></h1><p>Simpan lagu yang kamu suka, buat playlist untuk setiap suasana, dan biarkan musik mengalun.</p><div class="hero-art"><div class="hero-disc"><div></div></div><span class="hero-note">♪</span></div></div>
            <span class="hero-foot">© 2026 Aluna Music</span>
        </aside>
        <main class="account-main"><div class="account-top"><a href="{{ route('home') }}">← &nbsp; Kembali ke pemutar</a></div><div class="account-content">@yield('content')</div><div class="account-bottom">Aluna Music · Made for the way you listen.</div></main>
    </div>
</body>
</html>
