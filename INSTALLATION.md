# Panduan Instalasi

Aplikasi **Pengawasan Pembangunan Kapal** — Laravel 13 + Livewire 4.

## Requirements

- **PHP** 8.5 atau lebih tinggi
- **Composer** 2.x
- **Node.js** 18+ & npm
- **PostgreSQL** 17 / **MySQL** 8 / **SQLite**

## Langkah Instalasi

```bash
# 1. Install dependency PHP
composer install

# 2. Salin environment & generate key
cp .env.example .env
php artisan key:generate

# 3. Konfigurasi database di .env
#    Default: sqlite (file database/database.sqlite dibuat otomatis).
#    Untuk PostgreSQL/MySQL, sesuaikan DB_CONNECTION, DB_HOST, DB_PORT,
#    DB_DATABASE, DB_USERNAME, DB_PASSWORD.

# 4. Migrasi + seed (membuat akun demo & data simulasi lengkap)
php artisan migrate:fresh --seed

# 5. Frontend
npm install
npm run build

# 6. Jalankan
php artisan serve
```

## Akun Bawaan (dari seeder)

| Email | Password | Role |
|---|---|---|
| superadmin@app.com | password | Super Admin (bypass semua permission) |
| admin@app.com | password | Administrator |
| user@app.com | password | User |

## Background Services

Aplikasi memakai queue dan WebSocket; untuk fitur penuh jalankan:

```bash
# Wajib untuk proses file lampiran & generate dokumen Word (file_status berjalan via queue)
php artisan queue:work

# WebSocket untuk chat & notifikasi realtime
php artisan reverb:start
```

Tanpa `queue:work`, file lampiran akan tetap berstatus `pending`/`processing` dan dokumen Word tidak tergenerate. Alternatif lokal: set `QUEUE_CONNECTION=sync` di `.env`.

## Verifikasi

```bash
php artisan test                      # test suite (SQLite :memory:)
php artisan view:cache                # kompilasi Blade
npm run build                         # build aset
./vendor/bin/pint                     # code style
```

Catatan verifikasi proyek (perintah test laporan, gotchas Livewire/PhpWord): lihat `AGENTS.md`.

## Troubleshooting

### `view:cache` gagal "Access is denied" (Windows)
File view terkunci oleh dev server. Jalankan `php artisan view:clear` lalu ulangi.

### Migration error / extension hilang
- PostgreSQL: pastikan ekstensi `pdo_pgsql` aktif di `php.ini`.
- SQLite: pastikan `pdo_sqlite` aktif.
- Linux: `sudo apt-get install php8.5-mysql php8.5-pgsql php8.5-sqlite3 php8.5-gd php8.5-zip`.

### Permission denied pada storage/
```bash
# Linux
chmod -R 775 storage bootstrap/cache
```

### Log
Semua error aplikasi dicatat di `storage/logs/laravel.log`.
