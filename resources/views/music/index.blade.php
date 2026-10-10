<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('js/theme.js') }}"></script>
    <title>Aluna — ruang untuk musikmu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/music.css') }}?v={{ @filemtime(public_path('css/music.css')) ?: time() }}">
    <link rel="stylesheet" href="{{ asset('css/dark.css') }}?v={{ @filemtime(public_path('css/dark.css')) ?: time() }}">
</head>
<body>
    <svg class="icon-defs" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
        <symbol id="i-music" viewBox="0 0 24 24"><path d="M9 18V5l12-2v13M9 18a3 3 0 1 1-3-3c1.66 0 3 1.34 3 3Zm12-2a3 3 0 1 1-3-3c1.66 0 3 1.34 3 3Z"/></symbol>
        <symbol id="i-home" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V10Zm6 11v-8h6v8"/></symbol>
        <symbol id="i-heart" viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></symbol>
        <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></symbol>
        <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></symbol>
        <symbol id="i-upload" viewBox="0 0 24 24"><path d="M12 16V3m-5 5 5-5 5 5M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></symbol>
        <symbol id="i-play" viewBox="0 0 24 24"><path d="m8 5 11 7-11 7V5Z"/></symbol>
        <symbol id="i-pause" viewBox="0 0 24 24"><path d="M8 5v14M16 5v14"/></symbol>
        <symbol id="i-next" viewBox="0 0 24 24"><path d="m5 5 10 7-10 7V5Zm13 0v14"/></symbol>
        <symbol id="i-prev" viewBox="0 0 24 24"><path d="M19 5 9 12l10 7V5ZM6 5v14"/></symbol>
        <symbol id="i-volume" viewBox="0 0 24 24"><path d="M4 9v6h4l5 4V5L8 9H4Zm12-1a6 6 0 0 1 0 8m3-11a10 10 0 0 1 0 14"/></symbol>
        <symbol id="i-shuffle" viewBox="0 0 24 24"><path d="m17 3 4 4-4 4m4-4h-3c-6 0-6 10-12 10H3m14 4 4-4-4-4m4 4h-3c-6 0-6-10-12-10H3"/></symbol>
        <symbol id="i-repeat" viewBox="0 0 24 24"><path d="m17 2 4 4-4 4M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4m14-1v2a3 3 0 0 1-3 3H3"/></symbol>
        <symbol id="i-more" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></symbol>
        <symbol id="i-close" viewBox="0 0 24 24"><path d="M5 5 19 19M19 5 5 19"/></symbol>
        <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
        <symbol id="i-compass" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></symbol>
        <symbol id="i-mic" viewBox="0 0 24 24"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></symbol>
    </svg>

    <div class="app-shell">
        <aside class="sidebar">
            <a href="/" class="brand" aria-label="Aluna beranda"><span class="brand-icon"><svg><use href="#i-music"/></svg></span><span>aluna<span class="brand-dot">.</span></span></a>
            <div class="sidebar-section-label">MENU</div>
            <nav class="nav-list" aria-label="Menu utama">
                <button class="nav-item active" data-view="home"><svg><use href="#i-home"/></svg> Beranda</button>
                <button class="nav-item" data-view="explore"><svg><use href="#i-compass"/></svg> Jelajah Online</button>
                <button class="nav-item" data-view="library"><svg><use href="#i-grid"/></svg> Koleksi musik</button>
                <button class="nav-item" data-view="favorites"><svg><use href="#i-heart"/></svg> Lagu disukai</button>
            </nav>
            <div class="sidebar-divider"></div>
            <div class="sidebar-heading"><span>PLAYLIST KAMU</span><button id="new-playlist" class="plain-icon" title="Buat playlist" aria-label="Buat playlist"><svg><use href="#i-plus"/></svg></button></div>
            <nav id="playlist-nav" class="nav-list playlists" aria-label="Playlist"></nav>
            <div class="sidebar-bottom">
                <div class="sidebar-note"><span class="note-sparkle">✦</span><strong>Musik untuk setiap momen.</strong><span>Simpan lagu favoritmu dan putar kapan saja.</span></div>
                <div class="sidebar-footer">© 2026 Aluna Music</div>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar"><div class="mobile-brand"><span class="brand-icon"><svg><use href="#i-music"/></svg></span> aluna<span class="brand-dot">.</span></div><div class="search-field"><svg><use href="#i-search"/></svg><input id="search" type="search" placeholder="Cari lagu, artis, atau album..." aria-label="Cari lagu, artis, atau album"><kbd>⌘ K</kbd></div>@if(auth()->user()?->isAdministrator())<button id="upload-button" class="upload-button"><svg><use href="#i-upload"/></svg><span>Tambah lagu</span></button>@endif @auth<details class="account-menu"><summary class="avatar" aria-label="Menu akun {{ auth()->user()->name }}" title="{{ auth()->user()->name }}">@if(auth()->user()->avatar_path)<img src="{{ route('profile.avatar') }}" alt="">@else{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}@endif</summary><div class="account-dropdown"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small><a href="{{ route('profile') }}">Profil saya</a><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form></div></details>@else<a href="{{ route('login') }}" class="login-button">Masuk</a>@endauth</header>
            <div class="main-scroll">
                <nav class="mobile-nav" aria-label="Menu musik"><button class="active" data-view="home">Beranda</button><button data-view="explore">Jelajah</button><button data-view="library">Koleksi</button><button data-view="favorites">Disukai</button><button data-mobile-playlists="true">Playlist</button></nav>
                <div class="eyebrow"><span class="eyebrow-line"></span> SELAMAT DATANG DI ALUNA</div>
                <div class="page-title-row"><div><h1 id="page-title">Musik untuk harimu<span class="title-dot">.</span></h1><p id="page-subtitle">Temukan irama yang tepat untuk setiap suasana.</p></div><span id="date-label" class="date-label"></span></div>
                <section id="featured-section" class="featured-section">
                    <div class="section-header">
                        <div><span class="section-kicker">KATEGORI &amp; TREN</span><h2>Jelajah Musik Streaming</h2></div>
                        <div class="source-toggle" id="source-toggle">
                            <button class="source-btn active" data-source="online">✦ Online (Streaming)</button>
                            <button class="source-btn" data-source="local">📁 Koleksi Lokal</button>
                        </div>
                    </div>
                    <div id="category-grid" class="category-grid">
                        <button class="cat-card active" data-cat="Top Hits Indonesia 2026" style="--card-grad: linear-gradient(135deg, #ef4444, #f97316);">
                            <span class="cat-icon">🇮🇩</span>
                            <strong>Indonesia Hits</strong>
                            <small>Pop &amp; Viral Tanah Air</small>
                        </button>
                        <button class="cat-card" data-cat="Global Top 50 Hits 2026" style="--card-grad: linear-gradient(135deg, #3b82f6, #6366f1);">
                            <span class="cat-icon">🌎</span>
                            <strong>Global Top 50</strong>
                            <small>Tangga Lagu Dunia</small>
                        </button>
                        <button class="cat-card" data-cat="J-Pop Anime Hits Yoasobi" style="--card-grad: linear-gradient(135deg, #ec4899, #8b5cf6);">
                            <span class="cat-icon">🇯🇵</span>
                            <strong>J-Pop &amp; Anime</strong>
                            <small>Yoasobi, Kenshi, LiSA</small>
                        </button>
                        <button class="cat-card" data-cat="K-Pop Trending Hits 2026" style="--card-grad: linear-gradient(135deg, #a855f7, #ec4899);">
                            <span class="cat-icon">🇰🇷</span>
                            <strong>K-Pop Trending</strong>
                            <small>BTS, NewJeans, Aespa</small>
                        </button>
                        <button class="cat-card" data-cat="Band Pop Rock Indonesia Sheila On 7" style="--card-grad: linear-gradient(135deg, #eab308, #f97316);">
                            <span class="cat-icon">🎸</span>
                            <strong>Rock &amp; Band</strong>
                            <small>Sheila On 7, Dewa 19</small>
                        </button>
                        <button class="cat-card" data-cat="Acoustic Chill Indonesian" style="--card-grad: linear-gradient(135deg, #10b981, #06b6d4);">
                            <span class="cat-icon">☕</span>
                            <strong>Akustik &amp; Chill</strong>
                            <small>Suasana Santai &amp; Kafe</small>
                        </button>
                        <button class="cat-card" data-cat="Tulus Coldplay Taylor Swift" style="--card-grad: linear-gradient(135deg, #6366f1, #d946ef);">
                            <span class="cat-icon">🎤</span>
                            <strong>Artis Populer</strong>
                            <small>Tulus, Coldplay, Bernadya</small>
                        </button>
                        <button class="cat-card" data-cat="Album Terbaik Hits Indonesia" style="--card-grad: linear-gradient(135deg, #0ea5e9, #3b82f6);">
                            <span class="cat-icon">💿</span>
                            <strong>Album Pilihan</strong>
                            <small>Album Terbaik &amp; EP</small>
                        </button>
                    </div>
                </section>
                <section class="tracks-section"><div class="section-header track-header"><div><span class="section-kicker">KOLEKSI MUSIK</span><h2 id="list-heading">Daftar lagu</h2></div><span id="track-count" class="track-count"></span></div><div class="table-wrap"><div class="table-head"><span>#</span><span>JUDUL</span><span>ALBUM</span><span>WAKTU</span><span></span></div><div id="track-list" class="track-list"></div></div></section>
            </div>
        </main>

        <aside class="right-panel">
            <div id="yt-stream-card" class="yt-stream-card" style="display:none;">
                <div class="yt-stream-badge"><span class="pulse-dot"></span> LIVE STREAMING</div>
                <div class="yt-stream-box"><div id="yt-player"></div></div>
                <strong id="yt-stream-title" class="yt-stream-title"></strong>
                <small id="yt-stream-artist" class="yt-stream-artist"></small>
            </div>
            <div class="right-top"><div class="right-panel-label">✦ &nbsp; FOR YOUR EARS</div><h2>Musik<br>terbaru<span>.</span></h2><p>Temukan lagu yang siap menemani harimu.</p></div>
            <div id="recent-list" class="recent-list"></div>
            <div class="right-ornament"><div></div><div></div><div></div></div>
            <div class="right-bottom"><span>KEEP THE MUSIC PLAYING</span><span class="little-waves">▂ ▅ ▃ ▆ ▂ ▇ ▄</span></div>
        </aside>

        <footer class="player"><div class="player-progress-top"><div id="progress-fill"></div></div><div class="player-song"><img id="player-cover" src="{{ asset('covers/default.svg') }}" alt="Sampul lagu"><div class="player-song-text"><strong id="player-title">Belum ada lagu</strong><span id="player-artist">Pilih lagu untuk memutar</span></div><button id="player-like" class="plain-icon heart-button" title="Simpan ke koleksi" aria-label="Simpan ke koleksi"><svg><use href="#i-heart"/></svg></button></div><div class="player-center"><div class="player-controls"><button id="shuffle" class="plain-icon secondary-control" aria-label="Acak" title="Acak"><svg><use href="#i-shuffle"/></svg></button><button id="previous" class="plain-icon" aria-label="Lagu sebelumnya"><svg><use href="#i-prev"/></svg></button><button id="toggle-play" class="play-control" aria-label="Putar"><svg><use href="#i-play"/></svg></button><button id="next" class="plain-icon" aria-label="Lagu berikutnya"><svg><use href="#i-next"/></svg></button><button id="repeat" class="plain-icon secondary-control" aria-label="Ulangi" title="Ulangi"><svg><use href="#i-repeat"/></svg></button></div><div class="timeline"><span id="current-time">0:00</span><input id="seek" type="range" min="0" max="100" value="0" aria-label="Posisi lagu"><span id="total-time">0:00</span></div></div><div class="player-extra"><button id="lyrics-button" class="plain-icon secondary-control" aria-label="Lirik lagu" title="Lirik lagu"><svg><use href="#i-mic"/></svg></button><svg><use href="#i-volume"/></svg><input id="volume" type="range" min="0" max="100" value="75" aria-label="Volume"></div><audio id="audio" preload="none"></audio></footer>
    </div>

    <div id="modal-backdrop" class="modal-backdrop" hidden><div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title"><button id="modal-close" class="modal-close plain-icon" aria-label="Tutup"><svg><use href="#i-close"/></svg></button><div id="modal-content"></div></div></div>
    <div id="toast" class="toast" role="status" aria-live="polite"></div>

    <script src="https://www.youtube.com/iframe_api"></script>
    <script>window.MUSIC_DATA = @json($musicData);</script>
    <script src="{{ asset('js/music.js') }}?v={{ @filemtime(public_path('js/music.js')) ?: time() }}" defer></script>
</body>
</html>
