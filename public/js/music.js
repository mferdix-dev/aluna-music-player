(() => {
    const data = window.MUSIC_DATA;
    const tracks = data.tracks || [];
    let onlineTracks = (data.onlineTracks || []).map((t) => ({
        id: t.existing_id || t.youtube_id,
        youtube_id: t.youtube_id,
        title: t.title,
        artist: t.artist,
        album: t.album || 'YouTube Music',
        duration: t.duration || 0,
        cover: t.cover,
        audio: null,
        is_stream: true,
        demo: false,
        deletable: false,
    }));
    let streamTracks = [];

    const library = new Set((data.libraryIds || []).map(Number));
    const playlists = data.playlists || [];
    const $ = (selector) => document.querySelector(selector);
    const audio = $('#audio');

    let current = null;
    let view = 'home';
    let homeSource = 'online'; // Default: Online streaming songs directly on Beranda!
    let activeCategory = 'Top Hits Indonesia 2026';
    let query = '';
    let shuffle = false;
    let repeat = false;
    let menuId = null;
    let toastTimer;
    let isStreamLoading = false;

    // YouTube State
    let isYtActive = false;
    let ytPlayer = null;
    let ytReady = false;
    let pendingYtId = null;
    let ytTicker = null;

    // Lyrics State
    let activeLyrics = null;
    let parsedLyrics = [];

    const requireAccount = () => {
        if (data.authenticated) return true;
        window.location.href = data.loginUrl;
        return false;
    };

    const safe = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));

    const icon = (name) => `<svg><use href="#i-${name}"/></svg>`;

    const time = (seconds) => {
        const n = Number.isFinite(+seconds) ? Math.floor(+seconds) : 0;
        return `${Math.floor(n / 60)}:${String(n % 60).padStart(2, '0')}`;
    };

    const byId = (id) => {
        if (!id) return null;
        const idStr = String(id).trim();
        const idClean = idStr.replace(/^yt_/, '');

        const all = [...streamTracks, ...onlineTracks, ...tracks];
        return all.find((t) =>
            (t.id && String(t.id) === idStr) ||
            (t.id && String(t.id) === idClean) ||
            (t.youtube_id && String(t.youtube_id) === idClean) ||
            (t.youtube_id && String(t.youtube_id) === idStr)
        ) || null;
    };

    const notify = (message) => {
        const toast = $('#toast');
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 3300);
    };

    async function request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                ...(options.headers || {})
            }
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat().join(' ') || 'Terjadi kesalahan.');
        return result;
    }

    // YouTube IFrame API initialization
    window.onYouTubeIframeAPIReady = function() {
        if (ytPlayer) return;
        ytPlayer = new YT.Player('yt-player', {
            height: '110',
            width: '195',
            playerVars: {
                'autoplay': 1,
                'controls': 0,
                'disablekb': 1,
                'fs': 0,
                'playsinline': 1,
                'rel': 0,
                'origin': window.location.origin
            },
            events: {
                'onReady': () => {
                    ytReady = true;
                    if (ytPlayer.setVolume) ytPlayer.setVolume(75);
                    if (pendingYtId) {
                        ytPlayer.loadVideoById(pendingYtId);
                        ytPlayer.playVideo();
                        pendingYtId = null;
                    }
                },
                'onStateChange': onYtStateChange,
                'onError': (e) => {
                    console.error('YouTube playback error:', e);
                    notify('Audio streaming tidak dapat diputar dari sumber ini.');
                }
            }
        });
    };

    if (window.YT && window.YT.Player && !ytPlayer) {
        window.onYouTubeIframeAPIReady();
    }

    function onYtStateChange(event) {
        syncPlayer();
        render();
        if (event.data === YT.PlayerState.ENDED) {
            if (repeat) {
                ytPlayer.seekTo(0);
                ytPlayer.playVideo();
            } else {
                skip(1);
            }
        }
    }

    function visibleTracks() {
        if (view === 'explore') {
            return streamTracks;
        }
        if (view === 'library' || view === 'favorites') {
            return tracks.filter((t) => library.has(t.id));
        }
        if (view.startsWith('playlist:')) {
            const selected = playlists.find((p) => p.id === Number(view.split(':')[1]));
            return tracks.filter((t) => selected?.track_ids.includes(t.id));
        }
        // Home View
        let list = homeSource === 'online' ? onlineTracks : tracks;
        if (query) {
            list = list.filter((t) => `${t.title} ${t.artist} ${t.album}`.toLowerCase().includes(query));
        }
        return list;
    }

    function renderNav() {
        document.querySelectorAll('[data-view]').forEach((b) => b.classList.toggle('active', b.dataset.view === view));
        $('#playlist-nav').innerHTML = playlists.map((p) => `<button class="nav-item ${view === `playlist:${p.id}` ? 'active' : ''}" data-view="playlist:${p.id}">${safe(p.name)}</button>`).join('');
    }

    async function loadCategory(catQuery) {
        activeCategory = catQuery;
        isStreamLoading = true;
        render();
        try {
            const res = await request(`/stream/search?q=${encodeURIComponent(catQuery)}`);
            onlineTracks = (res.tracks || []).map((t) => ({
                id: t.existing_id || t.youtube_id,
                youtube_id: t.youtube_id,
                title: t.title,
                artist: t.artist,
                album: t.album || 'YouTube Music',
                duration: t.duration || 0,
                cover: t.cover,
                audio: null,
                is_stream: true,
                demo: false,
                deletable: false
            }));
        } catch (e) {
            notify('Gagal memuat lagu kategori.');
        } finally {
            isStreamLoading = false;
            render();
        }
    }

    async function searchStreaming(searchQuery) {
        if (!searchQuery) return;
        isStreamLoading = true;
        render();
        try {
            const res = await request(`/stream/search?q=${encodeURIComponent(searchQuery)}`);
            const mapped = (res.tracks || []).map((t) => ({
                id: t.existing_id || t.youtube_id,
                youtube_id: t.youtube_id,
                title: t.title,
                artist: t.artist,
                album: t.album || 'YouTube Music',
                duration: t.duration || 0,
                cover: t.cover,
                audio: null,
                is_stream: true,
                demo: false,
                deletable: false
            }));

            if (view === 'explore') {
                streamTracks = mapped;
            } else {
                onlineTracks = mapped;
            }
        } catch (e) {
            notify('Gagal mencari lagu streaming.');
        } finally {
            isStreamLoading = false;
            render();
        }
    }

    async function ensureTrackSaved(track) {
        if (track.id && typeof track.id === 'number') {
            return track;
        }
        try {
            const resolved = await request('/stream/resolve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    youtube_id: track.youtube_id,
                    title: track.title,
                    artist: track.artist,
                    album: track.album,
                    duration: track.duration,
                    cover: track.cover
                })
            });
            track.id = resolved.id;
            if (!tracks.find((t) => t.id === resolved.id)) {
                tracks.unshift(resolved);
            }
            return resolved;
        } catch (e) {
            notify('Gagal menyimpan track streaming.');
            throw e;
        }
    }

    function render() {
        renderNav();
        const selected = playlists.find((p) => view === `playlist:${p.id}`);
        const titles = {
            home: ['Musik untuk harimu', homeSource === 'online' ? `Daftar Lagu Online — ${activeCategory.replace(/\d{4}/g, '').trim()}` : 'Daftar Lagu Lokal'],
            explore: ['Jelajah Streaming YouTube Music', 'Hasil Pencarian Online'],
            library: ['Koleksi musikmu', 'Lagu di koleksi'],
            favorites: ['Lagu yang kamu suka', 'Lagu disukai']
        };
        const [page, listTitle] = selected ? [selected.name, `Lagu di ${selected.name}`] : (titles[view] || titles.home);
        $('#page-title').innerHTML = `${safe(page)}<span class="title-dot">.</span>`;
        $('#page-subtitle').textContent = view === 'home'
            ? 'Putar langsung lagu-lagu hits online dari YouTube Music atau koleksi lagu lokalmu.'
            : (view === 'explore' ? 'Jelajahi jutaan lagu dan album online secara bebas.' : 'Tempat semua lagu favoritmu berkumpul.');
        $('#list-heading').textContent = listTitle;

        // Toggle visibility of featured category section
        const featSec = $('#featured-section');
        if (featSec) {
            featSec.style.display = view === 'home' && !query ? '' : 'none';
        }

        // Sync source tabs (Online vs Lokal)
        document.querySelectorAll('.source-btn').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.source === homeSource);
        });

        // Sync category cards active state
        document.querySelectorAll('.cat-card').forEach((card) => {
            card.classList.toggle('active', card.dataset.cat === activeCategory);
        });

        const list = visibleTracks();
        $('#track-count').textContent = isStreamLoading ? 'Memuat lagu...' : `${list.length} lagu`;

        if (isStreamLoading) {
            $('#track-list').innerHTML = `<div class="empty-state"><strong>Sedang mengambil lagu dari YouTube Music...</strong>Harap tunggu sebentar.</div>`;
        } else if (list.length) {
            const isPlaying = isYtActive ? (ytPlayer?.getPlayerState?.() === 1) : !audio.paused;

            $('#track-list').innerHTML = list.map((track, index) => {
                const trackRef = track.youtube_id || track.id;
                const isCurrent = current && ((track.id && current.id === track.id) || (track.youtube_id && current.youtube_id === track.youtube_id));

                return `
                    <div class="track-row ${isCurrent ? 'playing' : ''}" data-play="${trackRef}" tabindex="0" role="button" aria-label="Putar ${safe(track.title)}">
                        <span class="row-number">${isCurrent && isPlaying ? '♫' : String(index + 1).padStart(2, '0')}</span>
                        <div class="row-main">
                            <img src="${safe(track.cover || '/covers/default.svg')}" alt="">
                            <span class="row-main-text">
                                <span class="track-name">${safe(track.title)}${track.youtube_id ? '<span class="stream-badge">STREAM</span>' : ''}</span>
                                <span class="track-sub">${safe(track.artist)}</span>
                            </span>
                        </div>
                        <span class="row-album">${safe(track.album)}</span>
                        <span class="row-time">${track.duration ? time(track.duration) : '—'}</span>
                        <span class="row-actions">
                            <button class="row-more plain-icon" data-menu="${trackRef}" aria-label="Opsi ${safe(track.title)}">${icon('more')}</button>
                            ${menuId === String(trackRef) ? `
                                <span class="track-menu">
                                    <button data-save="${trackRef}">${data.authenticated ? (typeof track.id === 'number' && library.has(track.id) ? 'Hapus dari koleksi' : 'Simpan ke koleksi') : 'Masuk untuk menyimpan'}</button>
                                    ${playlists.map((p) => `<button data-add="${p.id}:${trackRef}">${typeof track.id === 'number' && p.track_ids.includes(track.id) ? '✓ ' : '+ '}${safe(p.name)}</button>`).join('')}
                                    <button data-lyrics="${trackRef}">Lihat Lirik</button>
                                    ${track.deletable ? `<button data-delete="${track.id}">Hapus lagu</button>` : ''}
                                </span>
                            ` : ''}
                        </span>
                    </div>
                `;
            }).join('');
        } else {
            $('#track-list').innerHTML = `
                <div class="empty-state">
                    <strong>Belum ada lagu di sini</strong>
                    ${query ? 'Coba kata kunci lain atau pilih kategori di atas.' : 'Pilih kategori di atas untuk memuat lagu streaming online.'}
                </div>
            `;
        }

        // Recent items in right panel
        const sideItems = (onlineTracks.length ? onlineTracks : tracks).slice(0, 6);
        $('#recent-list').innerHTML = sideItems.map((track) => {
            const trackRef = track.youtube_id || track.id;
            const isCurrent = current && ((track.id && current.id === track.id) || (track.youtube_id && current.youtube_id === track.youtube_id));
            return `
                <button class="recent-item ${isCurrent ? 'active' : ''}" data-play="${trackRef}">
                    <img src="${safe(track.cover || '/covers/default.svg')}" alt="">
                    <span class="recent-info">
                        <strong>${safe(track.title)}</strong>
                        <small>${safe(track.artist)}</small>
                    </span>
                    <span class="recent-duration">${track.duration ? time(track.duration) : '—'}</span>
                </button>
            `;
        }).join('');

        const isSaved = current && typeof current.id === 'number' && library.has(current.id);
        $('#player-like').classList.toggle('saved', !!isSaved);
        $('#player-like').setAttribute('aria-label', isSaved ? 'Hapus dari koleksi' : 'Simpan ke koleksi');
    }

    function syncPlayer() {
        let isPaused = true;
        let curTime = 0;
        let totTime = current?.duration || 0;

        if (isYtActive && ytPlayer && typeof ytPlayer.getCurrentTime === 'function') {
            try {
                curTime = ytPlayer.getCurrentTime() || 0;
                totTime = ytPlayer.getDuration() || current?.duration || 0;
                const state = ytPlayer.getPlayerState();
                isPaused = state !== YT.PlayerState.PLAYING && state !== YT.PlayerState.BUFFERING;
            } catch (e) {}
        } else {
            isPaused = audio.paused;
            curTime = audio.currentTime;
            totTime = Number.isFinite(audio.duration) && audio.duration ? audio.duration : (current?.duration || 0);
        }

        $('#toggle-play').innerHTML = icon(isPaused ? 'play' : 'pause');
        $('#toggle-play').classList.toggle('paused-icon', !isPaused);
        $('#toggle-play').setAttribute('aria-label', isPaused ? 'Putar' : 'Jeda');
        $('#current-time').textContent = time(curTime);
        $('#total-time').textContent = time(totTime);

        const percent = totTime > 0 ? (curTime / totTime * 100) : 0;
        $('#seek').value = Math.min(100, Math.max(0, percent));
        $('#progress-fill').style.width = `${Math.min(100, Math.max(0, percent))}%`;

        updateActiveLyric(curTime);
    }

    function play(track) {
        if (!track) return;

        const isSame = current && (
            (track.id && current.id === track.id) ||
            (track.youtube_id && current.youtube_id === track.youtube_id)
        );

        if (!isSame) {
            current = track;
            $('#player-cover').src = track.cover || '/covers/default.svg';
            $('#player-title').textContent = track.title;
            $('#player-artist').textContent = track.artist;

            if (track.youtube_id) {
                // Stream YouTube Audio
                isYtActive = true;
                audio.pause();
                audio.removeAttribute('src');

                const ytCard = $('#yt-stream-card');
                if (ytCard) {
                    ytCard.style.display = 'block';
                    $('#yt-stream-title').textContent = track.title;
                    $('#yt-stream-artist').textContent = track.artist;
                }

                if (ytPlayer && ytReady && typeof ytPlayer.loadVideoById === 'function') {
                    ytPlayer.loadVideoById(track.youtube_id);
                    ytPlayer.playVideo();
                } else {
                    pendingYtId = track.youtube_id;
                    notify('Memulai streaming...');
                }

                if (!ytTicker) {
                    ytTicker = setInterval(syncPlayer, 300);
                }
            } else {
                // Play local file
                isYtActive = false;
                const ytCard = $('#yt-stream-card');
                if (ytCard) ytCard.style.display = 'none';

                if (ytPlayer && ytReady && typeof ytPlayer.stopVideo === 'function') {
                    try { ytPlayer.stopVideo(); } catch (e) {}
                }

                audio.src = track.audio || '';
                if (audio.src) {
                    audio.play().catch(() => notify('Audio tidak dapat diputar.'));
                } else {
                    notify('File audio tidak tersedia.');
                }
            }
        } else {
            // Toggle play/pause
            if (isYtActive && ytPlayer && typeof ytPlayer.getPlayerState === 'function') {
                const state = ytPlayer.getPlayerState();
                if (state === YT.PlayerState.PLAYING) {
                    ytPlayer.pauseVideo();
                } else {
                    ytPlayer.playVideo();
                }
            } else {
                if (audio.paused) {
                    audio.play().catch(() => notify('Audio tidak dapat diputar.'));
                } else {
                    audio.pause();
                }
            }
        }

        render();
        syncPlayer();
    }

    function skip(direction) {
        const list = visibleTracks().length ? visibleTracks() : (onlineTracks.length ? onlineTracks : tracks);
        if (!list.length) return;
        const index = list.findIndex((t) => (t.id && current?.id === t.id) || (t.youtube_id && current?.youtube_id === t.youtube_id));
        const next = shuffle && list.length > 1
            ? (index + 1 + Math.floor(Math.random() * (list.length - 1))) % list.length
            : (index + direction + list.length) % list.length;

        if (index === next && current) {
            if (isYtActive && ytPlayer) {
                ytPlayer.seekTo(0);
                ytPlayer.playVideo();
            } else {
                audio.currentTime = 0;
                audio.play().catch(() => {});
            }
            return;
        }
        current = null;
        play(list[next]);
    }

    async function toggleLibrary(track) {
        if (!requireAccount()) return;
        try {
            const savedTrack = await ensureTrackSaved(track);
            const res = await request(`/tracks/${savedTrack.id}/library`, { method: 'POST' });
            if (res.saved) {
                library.add(savedTrack.id);
            } else {
                library.delete(savedTrack.id);
            }
            menuId = null;
            render();
            notify(res.saved ? 'Ditambahkan ke koleksi.' : 'Dihapus dari koleksi.');
        } catch (e) {
            notify(e.message);
        }
    }

    async function showLyrics(track) {
        const target = track || current;
        if (!target) return notify('Pilih lagu terlebih dahulu.');

        openModal(`
            <h2>Lirik Lagu</h2>
            <p><strong>${safe(target.title)}</strong> — ${safe(target.artist)}</p>
            <div id="lyrics-content" class="lyrics-box">
                <div class="lyrics-empty">Mengambil lirik...</div>
            </div>
        `);

        try {
            const res = await request(`/stream/lyrics?title=${encodeURIComponent(target.title)}&artist=${encodeURIComponent(target.artist)}&duration=${target.duration || 0}`);
            const lyr = res.lyrics;
            const container = $('#lyrics-content');
            if (!container) return;

            if (!lyr || (!lyr.synced_lyrics && !lyr.plain_lyrics)) {
                container.innerHTML = `<div class="lyrics-empty">Lirik tidak ditemukan untuk lagu ini.</div>`;
                activeLyrics = null;
                parsedLyrics = [];
                return;
            }

            if (lyr.synced_lyrics) {
                parsedLyrics = [];
                const lines = lyr.synced_lyrics.split('\n');
                lines.forEach((l) => {
                    const m = l.match(/\[(\d{2}):(\d{2}(?:\.\d+)?)\](.*)/);
                    if (m) {
                        parsedLyrics.push({ time: parseInt(m[1], 10) * 60 + parseFloat(m[2]), text: m[3].trim() });
                    }
                });

                container.innerHTML = parsedLyrics.map((item, idx) => `
                    <div class="lyrics-line" data-lyric-idx="${idx}" data-time="${item.time}">
                        ${safe(item.text || '♪ ♪ ♪')}
                    </div>
                `).join('');

                container.addEventListener('click', (e) => {
                    const row = e.target.closest('.lyrics-line');
                    if (row && row.dataset.time) {
                        const targetTime = parseFloat(row.dataset.time);
                        if (isYtActive && ytPlayer && ytPlayer.seekTo) {
                            ytPlayer.seekTo(targetTime, true);
                        } else {
                            audio.currentTime = targetTime;
                        }
                    }
                });

                activeLyrics = { title: target.title, lines: parsedLyrics };
            } else {
                parsedLyrics = [];
                activeLyrics = null;
                container.innerHTML = `<div class="lyrics-plain">${safe(lyr.plain_lyrics)}</div>`;
            }
        } catch (e) {
            const container = $('#lyrics-content');
            if (container) container.innerHTML = `<div class="lyrics-empty">Gagal memuat lirik: ${safe(e.message)}</div>`;
        }
    }

    function updateActiveLyric(currentTime) {
        if (!activeLyrics || !parsedLyrics.length) return;
        const container = $('#lyrics-content');
        if (!container) return;

        let activeIdx = -1;
        for (let i = 0; i < parsedLyrics.length; i++) {
            if (currentTime >= parsedLyrics[i].time) {
                activeIdx = i;
            } else {
                break;
            }
        }

        const lines = container.querySelectorAll('.lyrics-line');
        lines.forEach((el, idx) => {
            if (idx === activeIdx) {
                if (!el.classList.contains('active')) {
                    el.classList.add('active');
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else {
                el.classList.remove('active');
            }
        });
    }

    const modal = $('#modal-backdrop');
    let uploadDragController = null;
    const closeModal = () => {
        uploadDragController?.abort();
        uploadDragController = null;
        modal.hidden = true;
        $('#modal-content').innerHTML = '';
        activeLyrics = null;
        parsedLyrics = [];
    };

    function openModal(html) {
        $('#modal-content').innerHTML = html;
        modal.hidden = false;
        $('#modal-content input')?.focus();
    }

    // Global Click Handler
    document.addEventListener('click', async (event) => {
        const target = event.target;

        // Source Toggle (Online vs Lokal)
        const sourceBtn = target.closest('[data-source]');
        if (sourceBtn) {
            homeSource = sourceBtn.dataset.source;
            render();
            return;
        }

        // Category Cards Click
        const catBtn = target.closest('[data-cat]');
        if (catBtn) {
            loadCategory(catBtn.dataset.cat);
            return;
        }

        const action = target.closest('[data-view],[data-play],[data-menu],[data-save],[data-add],[data-delete],[data-lyrics],[data-quick-search]');

        if (action?.dataset.quickSearch) {
            const q = action.dataset.quickSearch;
            $('#search').value = q;
            searchStreaming(q);
            return;
        }

        if (action?.dataset.view) {
            view = action.dataset.view;
            query = '';
            $('#search').value = '';
            menuId = null;
            if (!modal.hidden) closeModal();
            if (view === 'explore' && !streamTracks.length) {
                searchStreaming('Top Hits Indonesia 2026');
            } else {
                render();
            }
            return;
        }

        if (action?.dataset.lyrics) {
            event.stopPropagation();
            const track = byId(action.dataset.lyrics);
            menuId = null;
            render();
            showLyrics(track);
            return;
        }

        if (action?.dataset.menu) {
            event.stopPropagation();
            const idVal = String(action.dataset.menu);
            menuId = menuId === idVal ? null : idVal;
            render();
            return;
        }

        if (action?.dataset.save) {
            event.stopPropagation();
            await toggleLibrary(byId(action.dataset.save));
            return;
        }

        if (action?.dataset.add) {
            event.stopPropagation();
            const [playlistId, trackRef] = action.dataset.add.split(':');
            const targetTrack = byId(trackRef);
            const playlist = playlists.find((p) => p.id === Number(playlistId));
            try {
                const savedTrack = await ensureTrackSaved(targetTrack);
                const res = await request(`/playlists/${playlistId}/tracks/${savedTrack.id}`, { method: 'POST' });
                if (res.saved) {
                    if (!playlist.track_ids.includes(savedTrack.id)) playlist.track_ids.push(savedTrack.id);
                } else {
                    playlist.track_ids = playlist.track_ids.filter((id) => id !== savedTrack.id);
                }
                menuId = null;
                render();
                notify(res.saved ? `Ditambahkan ke ${playlist.name}.` : `Dihapus dari ${playlist.name}.`);
            } catch (e) {
                notify(e.message);
            }
            return;
        }

        if (action?.dataset.delete) {
            event.stopPropagation();
            const track = byId(action.dataset.delete);
            if (!confirm(`Hapus "${track.title}" secara permanen?`)) return;
            try {
                await request(`/tracks/${track.id}`, { method: 'DELETE' });
                if (current?.id === track.id) {
                    audio.pause();
                    audio.removeAttribute('src');
                    if (isYtActive && ytPlayer) ytPlayer.stopVideo();
                    current = null;
                    $('#player-title').textContent = 'Belum ada lagu';
                    $('#player-artist').textContent = 'Pilih lagu untuk memutar';
                    $('#player-cover').src = '/covers/default.svg';
                }
                tracks.splice(tracks.indexOf(track), 1);
                menuId = null;
                render();
                syncPlayer();
                notify('Lagu berhasil dihapus.');
            } catch (e) {
                notify(e.message);
            }
            return;
        }

        if (action?.dataset.play) {
            const track = byId(action.dataset.play);
            if (track) {
                play(track);
            }
            return;
        }

        if (menuId !== null) {
            menuId = null;
            render();
        }
    });

    let searchDebounce = null;
    $('#search').addEventListener('input', (event) => {
        query = event.target.value.trim().toLowerCase();
        clearTimeout(searchDebounce);
        if (query.length >= 2) {
            searchDebounce = setTimeout(() => {
                if (view === 'explore' || homeSource === 'online') {
                    searchStreaming(query);
                } else {
                    render();
                }
            }, 600);
        } else {
            render();
        }
    });

    $('#search').addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && $('#search').value.trim()) {
            event.preventDefault();
            searchStreaming($('#search').value.trim());
        }
    });

    $('#view-all')?.addEventListener('click', () => $('#track-list').scrollIntoView({ behavior: 'smooth', block: 'start' }));
    $('#toggle-play').addEventListener('click', () => current ? play(current) : play(visibleTracks()[0]));
    $('#previous').addEventListener('click', () => {
        const curTime = isYtActive ? (ytPlayer?.getCurrentTime?.() || 0) : audio.currentTime;
        if (curTime > 3) {
            if (isYtActive && ytPlayer) ytPlayer.seekTo(0);
            else audio.currentTime = 0;
        } else {
            skip(-1);
        }
    });
    $('#next').addEventListener('click', () => skip(1));
    $('#shuffle').addEventListener('click', () => {
        shuffle = !shuffle;
        $('#shuffle').classList.toggle('active', shuffle);
        notify(shuffle ? 'Mode acak aktif.' : 'Mode acak nonaktif.');
    });
    $('#repeat').addEventListener('click', () => {
        repeat = !repeat;
        $('#repeat').classList.toggle('active', repeat);
        notify(repeat ? 'Ulangi lagu aktif.' : 'Ulangi lagu nonaktif.');
    });
    $('#player-like').addEventListener('click', () => current ? toggleLibrary(current) : notify('Pilih lagu terlebih dahulu.'));
    $('#lyrics-button')?.addEventListener('click', () => showLyrics());

    $('#seek').addEventListener('input', (event) => {
        const percent = event.target.value;
        const tot = isYtActive ? (ytPlayer?.getDuration?.() || current?.duration || 0) : audio.duration;
        if (Number.isFinite(tot) && tot > 0) {
            const targetSec = tot * percent / 100;
            if (isYtActive && ytPlayer && ytPlayer.seekTo) {
                ytPlayer.seekTo(targetSec, true);
            } else {
                audio.currentTime = targetSec;
            }
        }
    });

    $('#volume').addEventListener('input', (event) => {
        const val = event.target.value;
        audio.volume = val / 100;
        if (ytPlayer && ytReady && ytPlayer.setVolume) {
            ytPlayer.setVolume(val);
        }
    });

    audio.volume = 0.75;
    audio.addEventListener('timeupdate', syncPlayer);
    audio.addEventListener('loadedmetadata', syncPlayer);
    audio.addEventListener('play', () => { syncPlayer(); render(); });
    audio.addEventListener('pause', () => { syncPlayer(); render(); });
    audio.addEventListener('ended', () => {
        if (repeat) {
            audio.currentTime = 0;
            audio.play();
        } else {
            skip(1);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
            menuId = null;
            render();
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            $('#search').focus();
        }
    });

    $('#modal-close').addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });

    $('#new-playlist')?.addEventListener('click', () => {
        if (!requireAccount()) return;
        openModal(`<h2>Playlist baru</h2><p>Beri nama playlist untuk mengumpulkan lagu favoritmu.</p><form id="playlist-form"><label class="form-field">Nama playlist<input name="name" maxlength="80" placeholder="Contoh: Teman perjalanan" required></label><div class="form-error" id="form-error"></div><button class="form-submit">Buat playlist</button></form>`);
        $('#playlist-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            try {
                const playlist = await request('/playlists', { method: 'POST', body: new FormData(e.target) });
                playlists.push(playlist);
                view = `playlist:${playlist.id}`;
                closeModal();
                render();
                notify('Playlist berhasil dibuat.');
            } catch (err) {
                $('#form-error').textContent = err.message;
                btn.disabled = false;
            }
        });
    });

    $('#date-label').textContent = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

    // If onlineTracks was empty initially, trigger loading of Top Hits
    if (!onlineTracks.length) {
        loadCategory('Top Hits Indonesia 2026');
    } else {
        render();
    }
})();
