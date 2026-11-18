# FTS Hotel AI

Situs hotel interaktif dengan **AI Concierge**. Tamu menjelajahi hotel seperti berjalan di lobi (lobi, resepsionis, kamar, fasilitas, informasi, reservasi) sambil mengobrol dengan concierge AI. Concierge menjawab dari data hotel yang terkontrol, mengecek ketersediaan kamar, membuat permintaan booking, dan menyerahkan percakapan ke staf bila perlu. Staf mengelola semuanya lewat panel admin.

Satu aplikasi bisa melayani beberapa hotel. Setiap hotel punya halaman sendiri di `/{hotelSlug}`.

## Fitur

### Sisi tamu
- **Halaman bertema "stage"**: lobi, kamar, detail kamar, fasilitas, info hotel, staf, dan reservasi, dengan narrator dan efek suara (tombol bisa dimatikan).
- **Tiga bahasa**: Indonesia (`id`), Inggris (`en`), dan Jepang (`ja`), dipilih lewat parameter `?lang=`.
- **Chat dengan AI Concierge**
  - Tahu di halaman mana tamu berada (kamar atau fasilitas yang sedang dilihat, form reservasi yang sedang diisi), jadi bisa menjawab "kamar ini".
  - Menjawab dalam bahasa yang ditulis tamu.
  - Menampilkan kartu kamar atau fasilitas langsung di dalam chat.
- **Reservasi**: quote harga per kamar dan tanggal, lalu kirim permintaan booking (status awal `pending`).

### Sisi admin (`/admin`)
- Dashboard ringkasan.
- CRUD **tipe kamar** (termasuk gambar dan inventori).
- CRUD **knowledge items**, yaitu basis pengetahuan yang menjadi sumber jawaban concierge (kebijakan, fasilitas, restoran, transportasi, FAQ).
- Daftar **booking** dan ubah statusnya (`pending`, `confirmed`, `cancelled`).
- **Handover**: percakapan yang diserahkan concierge ke staf. Staf bisa membalas langsung ke tamu dan menandainya selesai.
- Peran pengguna per hotel: `owner` dan `staff`.

## Cara kerja AI Concierge

Kode ada di [app/Services/Concierge/](app/Services/Concierge).

- **[ConciergeService](app/Services/Concierge/ConciergeService.php)** menjalankan satu giliran percakapan. Ia memanggil endpoint chat yang kompatibel dengan OpenAI (LM Studio atau Ollama yang di-host sendiri) dan menjalankan loop tool-calling (maksimal 6 putaran per giliran). Riwayat percakapan disimpan di database. Request dikirim dengan `reasoning_effort=none` agar model tidak melakukan fase berpikir panjang. Seluruh giliran dibatasi 85 detik supaya muat dalam batas 100 detik Cloudflare.
- **[HotelConciergeTools](app/Services/Concierge/HotelConciergeTools.php)** berisi alat yang bisa dipanggil model. Semua fakta hotel, kamar, harga, dan ketersediaan harus lewat sini, karena model sendiri tidak dipercaya menyimpan data apa pun.

  | Tool | Fungsi |
  |---|---|
  | `search_knowledge` | Mencari di knowledge base hotel |
  | `search_rooms` | Mencari kamar sesuai tanggal dan jumlah tamu, lengkap dengan ketersediaan dan total harga |
  | `get_room_detail` | Detail satu tipe kamar |
  | `check_availability` | Cek ketersediaan kamar untuk tanggal tertentu |
  | `create_booking_request` | Membuat permintaan booking |
  | `request_human_handover` | Menyerahkan percakapan ke staf |

- **[ContentGuard](app/Services/Concierge/ContentGuard.php)** adalah pengaman deterministik di kode. Pesan yang kasar, bersifat seksual, atau ilegal (Indonesia, Inggris, Jepang) dijawab dengan balasan baku tanpa sampai ke model. Balasan model yang masih memuat kata-kata tersebut juga diganti. Pola dibuat sempit supaya pertanyaan hotel yang wajar tidak ikut terblokir.
- **Harga tanpa sumber ditolak**: balasan yang memuat angka harga atau placeholder yang tidak berasal dari tool akan ditantang, supaya model tidak mengarang harga.
- **Handover**: alasan yang didukung adalah `special_request`, `complaint`, `group_booking`, `negotiated_rate`, `unusual_cancellation`, `payment_issue`, dan `low_confidence`.

## Teknologi

- PHP 8.3+ (dikembangkan di 8.4), Laravel 13
- SQLite sebagai database bawaan. Session, cache, dan queue memakai driver `database`
- Vite 8 dan Tailwind CSS 4 untuk frontend, dengan JavaScript vanilla (tanpa framework) di `resources/js/`
- LLM lokal lewat HTTP (endpoint kompatibel OpenAI)
- PHPUnit 12 untuk tes, Laravel Pint untuk format kode

## Memulai

### Prasyarat
PHP 8.3+ dengan ekstensi SQLite, Composer, dan Node.js 20.19+ (atau 22.12+) dengan npm. Untuk fitur chat AI, Anda juga perlu server LLM yang kompatibel dengan OpenAI (lihat bagian berikutnya).

### Instalasi

```bash
composer setup
```

Perintah itu menjalankan `composer install`, menyalin `.env.example` ke `.env`, membuat `APP_KEY`, menjalankan migrasi, lalu `npm install` dan `npm run build`.

Isi data demo (hanya jalan di environment `local` dan `testing`):

```bash
php artisan db:seed
```

Seeder membuat hotel demo **FTS Hotel AI** (slug `fts-hotel-ai`) dengan beberapa tipe kamar, fasilitas, dan knowledge items.

### Menjalankan

```bash
composer dev
```

Perintah ini menjalankan semua proses development lewat `php artisan dev`. Daftar prosesnya bisa dilihat dengan `php artisan dev:list`.

| Halaman | URL |
|---|---|
| Halaman pembuka | `http://localhost:8000/` |
| Hotel demo | `http://localhost:8000/fts-hotel-ai` |
| Admin | `http://localhost:8000/admin` |

Akun admin demo (hanya untuk lokal, jangan dipakai di produksi):

```
email    : owner@ftshotel.test
password : password
```

### Konfigurasi LLM

Atur di `.env`:

```dotenv
LOCAL_LLM_BASE_URL=   # mis. http://127.0.0.1:1234/v1 (LM Studio) atau http://127.0.0.1:11434/v1 (Ollama)
LOCAL_LLM_API_KEY=    # kosongkan jika server tidak memakai autentikasi
LOCAL_LLM_MODEL=      # nama model yang dimuat di server
```

Model harus mendukung function calling. Tanpa konfigurasi ini, halaman hotel tetap jalan tetapi chat concierge tidak bisa menjawab.

Kalau perlu, ubah juga `APP_NAME`, `APP_URL`, dan `APP_TIMEZONE` (bawaan `Asia/Jakarta`).

### Mencoba concierge dari terminal

```bash
php artisan concierge:chat fts-hotel-ai --locale=id
```

Pilihan `--locale` adalah `id`, `en`, atau `ja`. Cara ini berguna untuk menguji loop tool-calling tanpa membuka browser.

## Endpoint utama

| Method | Path | Keterangan |
|---|---|---|
| GET | `/{hotelSlug}` | Lobi hotel |
| GET | `/{hotelSlug}/rooms`, `/rooms/{roomSlug}` | Daftar dan detail kamar |
| GET | `/{hotelSlug}/facilities`, `/facilities/{id}` | Fasilitas |
| GET | `/{hotelSlug}/info`, `/staff`, `/reservation` | Info, staf, reservasi |
| POST | `/{hotelSlug}/reservation/quote` | Hitung harga |
| POST | `/{hotelSlug}/reservation` | Kirim permintaan booking |
| POST | `/{hotelSlug}/concierge/start` | Mulai percakapan |
| POST | `/{hotelSlug}/concierge/message` | Kirim pesan ke concierge |
| GET | `/{hotelSlug}/concierge/history` | Riwayat percakapan |

Endpoint concierge dan reservasi dibatasi laju (rate limit). Pembatas concierge didefinisikan di [AppServiceProvider](app/Providers/AppServiceProvider.php), dan login admin dibatasi 5 percobaan per menit.

## Struktur proyek

```
app/
  Console/Commands/     concierge:chat
  Http/Controllers/     halaman tamu, chat, reservasi, dan Admin/
  Models/               Hotel, RoomType, Booking, Conversation, HandoverRequest, ...
  Services/Concierge/   ConciergeService, HotelConciergeTools, ContentGuard
  Services/Reservation/ ReservationService, ReservationHandover
database/
  migrations/           skema (hotel, kamar, knowledge, percakapan, booking, handover)
  seeders/              DemoHotelSeeder
resources/
  js/                   stage, narrator, concierge, reservation, sound
  views/                halaman tamu (hotel/), admin (admin/), komponen
tests/
  Feature/ dan Unit/
```

## Pengujian

```bash
composer test
```

Atau jalankan satu berkas:

```bash
php artisan test --compact tests/Feature/ConciergeChatTest.php
```

Cakupan tes: akses admin, chat concierge, tool booking, permintaan reservasi, halaman lobi, dan `ContentGuard`.

## Gaya kode

```bash
vendor/bin/pint --dirty
```

## Deployment

- Jalankan `npm run build` dan `php artisan migrate --force`.
- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Seeder demo tidak jalan di produksi. Buat akun owner dan hotel Anda sendiri.
- Pastikan server aplikasi bisa menjangkau `LOCAL_LLM_BASE_URL`. Pada setup saat ini endpoint LLM diakses lewat Tailscale.
- Karena ada batas 100 detik dari Cloudflare, jangan menaikkan batas waktu respons concierge melebihi 85 detik.

## Lisensi

Project ini dibangun di atas [Laravel](https://laravel.com), yang berlisensi [MIT](https://opensource.org/licenses/MIT).

## Deploy

Push to `master` runs the tests, then pushes to the Plesk Git repo over SSH with a deploy key that can only push to `hotel-ai.git` (forced command, no shell). Secrets: `PLESK_SSH_PRIVATE_KEY`, `PLESK_SSH_KNOWN_HOSTS`, `PLESK_REMOTE_URL`.
