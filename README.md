# Aluna — Music Player

Aplikasi music player Laravel dengan database SQLite. Tampilan memenuhi seluruh jendela browser tanpa bingkai atau latar luar, dengan tiga kolom pada desktop dan antarmuka mobile responsif. Tersedia pemutar audio, pencarian, koleksi lagu, playlist, login, pendaftaran, profil, serta tema gelap otomatis mengikuti jam komputer.

## Menjalankan

```powershell
cd 'C:\laravel\project\Music Player'
composer install
php artisan migrate --seed
php artisan serve
```

Buka `http://127.0.0.1:8000`. Proyek sudah memiliki `.env`, `APP_KEY`, database SQLite, migrasi, dan data contoh. Jalankan `php artisan test` untuk pengujian. Tidak perlu Node/Vite untuk tampilan ini.

Unit test model ada di `tests/Unit/MusicModelsTest.php` (sampul default/demo/unggahan, URL audio, serta relasi playlist). Pengujian HTTP, login, profil, dan isolasi data antar akun ada di `tests/Feature`.

## Administrator

Administrator adalah akun terpisah yang dibuat melalui terminal, **bukan melalui halaman daftar**. Untuk membuat admin baru dengan kata sandi sementara acak:

```powershell
php artisan music:create-admin
# atau: php artisan music:create-admin adminlain@example.com
```

Akun pertama `admin@aluna.local` sudah dibuat pada instalasi ini. Kata sandi sementara ditampilkan saat akun dibuat; segera ganti melalui **Profil → Ganti kata sandi**. Perintah tidak akan mengubah hak akses akun yang sudah terdaftar. Pendaftaran publik selalu membuat akun biasa.

## Penggunaan

- Klik lagu atau sampul untuk memutar. Gunakan kontrol bawah untuk jeda, lanjut, lagu berikutnya/sebelumnya, acak, ulangi, posisi, dan volume.
- Tanpa akun, pengunjung dapat melihat, mencari, dan memutar lagu contoh secara online. Klik **Masuk** untuk login atau daftar akun baru.
- Setelah login, klik foto/inisial profil untuk membuka profil atau keluar. Profil mendukung perubahan nama, email, foto (maksimal 2 MB), dan kata sandi.
- Hanya administrator yang melihat tombol **Tambah lagu**. Isi judul dan artis, lalu **seret dan lepas file audio** (maksimal 20 MB) ke area unggah; klik area tersebut tetap bisa dipakai jika diperlukan. Sampul tidak wajib diunggah: sistem mencari sampul di katalog iTunes berdasarkan **judul dan artis yang cocok**, menyimpannya secara lokal, lalu memakai sampul bawaan jika tidak ditemukan atau sedang offline. Sampul manual opsional dan diutamakan dibanding hasil pencarian. Lagu admin tersedia untuk semua pengunjung, termasuk yang belum login. Audio tersimpan di `storage/app/private/audio`; sampul di `storage/app/private/covers`. Berkas disajikan lewat rute Laravel, tidak memerlukan `storage:link`.
- Klik **•••** pada lagu untuk menyimpan/menghapus dari koleksi, memasukkan/mengeluarkan lagu dari playlist, atau menghapus lagu unggahan.
- Klik **+** pada sidebar untuk membuat playlist; gunakan pencarian untuk menemukan lagu berdasarkan judul, artis, atau album.
- Lagu contoh menggunakan audio dari SoundHelix dan membutuhkan koneksi internet. Sampul contoh tersedia secara lokal di `public/covers`.

Database menggunakan `users`, `tracks`, `playlists`, `playlist_track`, dan `user_library_tracks`. Koleksi dan playlist terpisah berdasarkan akun; lagu baru dari administrator bersifat publik. Unggahan privat lama tetap hanya terlihat oleh pemiliknya. Tabel `library_tracks` dan playlist lama tanpa pemilik tetap dipertahankan sebagai data lama, tetapi tidak ditampilkan di akun baru.

Tema mengikuti **waktu lokal pada perangkat pengunjung**: terang pukul 06.00–17.59, gelap pukul 18.00–05.59. Tema diperbarui otomatis setiap menit dan ketika tab dibuka kembali.

## Persiapan deployment Vercel

Proyek memiliki `vercel.json` dan `api/index.php` untuk runtime PHP komunitas `vercel-php`. **Konfigurasi ini belum diterbitkan atau diverifikasi lewat Vercel**; deployment memerlukan akses akun dan layanan cloud berikut. Vercel tidak menyediakan disk persisten bagi Laravel: database SQLite dan `storage/app/private` lokal tidak boleh digunakan di production.

1. Siapkan PostgreSQL/MySQL terkelola, dan bucket privat S3-compatible yang mendukung presigned PUT/GET. Beri izin `GetObject`, `PutObject`, dan `DeleteObject` pada prefix `audio/`, `covers/`, `avatars/`. Konfigurasikan CORS bucket untuk domain Vercel (origin produksi dan preview yang dipakai), metode `PUT` dan `GET`, serta header `Content-Type`; jangan memakai wildcard origin pada produksi bila tidak diperlukan.
2. Masuk ke Vercel dan pasang CLI (`npm install -g vercel`) **di komputer yang sudah memiliki Node.js**, atau hubungkan repositori lewat dashboard. Jalankan perintah deploy dari folder `Music Player`, bukan dari induk `C:\laravel\project`.
3. Pasang variabel environment **Production** di Vercel (jangan menaruh nilainya di `vercel.json` atau Git):

   | Variabel | Nilai |
   | --- | --- |
   | `APP_ENV`, `APP_DEBUG` | `production`, `false` |
   | `APP_KEY` | kunci baru dari `php artisan key:generate --show` |
   | `APP_URL` | `https://domain-aplikasi.vercel.app` |
   | `DB_CONNECTION`, `DB_URL` | `pgsql` atau `mysql`, dan URL koneksi database cloud (gunakan nama **DB_URL**, bukan DATABASE_URL) |
   | `MUSIC_DISK` | `s3` |
   | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | kredensial bucket |
   | `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT` | region, nama bucket, dan endpoint S3-compatible (endpoint boleh dikosongkan untuk AWS S3) |
   | `AWS_USE_PATH_STYLE_ENDPOINT` | `true` bila diminta oleh penyedia bucket |
   | `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | `database`, `database`, `sync` |
   | `SESSION_SECURE_COOKIE` | `true` |
   | `LOG_CHANNEL` | `stderr` |

4. Jalankan migrasi dan seed **sekali** pada database cloud dari mesin yang memiliki aksesnya: `php artisan migrate --force` lalu `php artisan db:seed --force`. Buat akun administrator cloud dengan `php artisan music:create-admin` setelah konfigurasi database diarahkan ke cloud. Admin yang ada di SQLite lokal **tidak otomatis tersalin** ke database cloud.
5. Deploy dengan `vercel --prod`. Verifikasi URL, login, pencarian, pemutaran, serta unggah dari admin. Untuk cloud, audio 20 MB diunggah langsung dari browser ke bucket menggunakan URL sementara sehingga tidak melewati batas request Vercel 4,5 MB. Sampul manual dibatasi 2 MB; pencarian sampul otomatis tetap tersedia.

Jangan unggah `.env`, file SQLite, atau `vendor` lokal. File-file tersebut dikecualikan oleh `.vercelignore`; perubahan database lokal tidak memengaruhi database cloud.
