# Deployment (Docker + GitLab CI/CD)

Satu image (`Dockerfile` multi-stage) dipakai 4 service: `app` (nginx + php-fpm),
`worker` (queue), `reverb` (websocket), `scheduler`. Database PostgreSQL
**eksternal** — bukan bagian dari compose produksi.

## File

| File | Fungsi |
|------|--------|
| `Dockerfile` | Multi-stage: `base` → `vendor` → `assets` → `test` → `production` |
| `docker/entrypoint.sh` | `storage:link` + `config:cache` + `view:cache` (www-data), migrasi via `MIGRATE_ON_START` |
| `docker/nginx.conf` | vhost nginx → php-fpm (`127.0.0.1:9000`) |
| `docker/supervisord.conf` | php-fpm + nginx dalam satu container `app` |
| `docker/php.ini` | OPcache + limit upload 64M |
| `docker-compose.yml` | Produksi: app, worker, reverb, scheduler |
| `docker-compose.local.yml` | Override dev: tambah service `postgres` |
| `.gitlab-ci.yml` | Pipeline test → build&push registry → deploy SSH |

## Setup server produksi (sekali)

```bash
sudo mkdir -p /opt/simpro && cd /opt/simpro
# copy docker-compose.yml ke sini, lalu buat .env:
cp .env.example .env   # lalu edit
```

Isi `.env` minimum untuk produksi:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.domain.com
APP_KEY=base64:...            # php artisan key:generate --show

DB_CONNECTION=pgsql
DB_HOST=<db-server>
DB_PORT=5432
DB_DATABASE=simpro
DB_USERNAME=simpro
DB_PASSWORD=...

# SSO (kontrak dengan sso-server tidak berubah)
IS_USING_SSO=true
SSO_CLIENT_ID=...
SSO_CLIENT_SECRET=...
SSO_BASE_URL=https://sso.domain.com
SSO_REDIRECT_URI=https://app.domain.com/auth/callback
SSO_API_SECRET=...

# Reverb — host/port publik yang diakses browser
REVERB_APP_ID=1001
REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<random>
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

# Host internal untuk koneksi PHP -> Reverb (publish event dari app/worker).
# Default compose sudah `reverb` (nama service). Isi manual HANYA bila
# Reverb berjalan di luar jaringan compose ini.
# REVERB_BROADCAST_HOST=reverb
# REVERB_BROADCAST_PORT=8080
# REVERB_BROADCAST_SCHEME=http
```

Deploy manual pertama:

```bash
cd /opt/simpro
docker login gitlab.domain.com:5050   # registry kantor
APP_IMAGE=gitlab.domain.com:5050/group/repo:latest docker compose pull
APP_IMAGE=gitlab.domain.com:5050/group/repo:latest docker compose up -d
```

- `MIGRATE_ON_START=true` (default di service `app`) menjalankan
  `php artisan migrate --force` tiap container start — aman/idempotent,
  hanya di service `app` (worker/scheduler/reverb tidak ikut migrasi).
- `SEED_ON_START=true` (default di service `app`) menjalankan seeder inti
  yang idempotent: `PermissionSeeder`, `RoleSeeder`,
  `SystemConfigurationSeeder`, `KelembabanSeeder`, `CuacaSeeder`,
  `UserSeeder`. Deploy pertama otomatis mendapat roles + akun default
  (lihat seeder). Data demo laporan **tidak** ikut — bila perlu jalankan
  manual `docker compose exec app php artisan db:seed`.
- `.env` di `env_file` bersifat opsional (`required: false`): tanpa `.env`
  entrypoint tetap membuat `.env` dari `.env.example` dan men-generate
  `APP_KEY` bila kosong, sehingga container selalu bisa boot. Untuk
  produksi **tetap sediakan `.env`** dengan `APP_KEY` stabil (key yang
  di-generate otomatis berubah tiap container recreate dan meng-invalidate
  session/encrypted route key), `APP_URL`, `DB_*`, dan `SSO_*`.
- Named volume `storage_app` di-seed dari image (template docx default ikut),
  lalu menampung lampiran/template custom/docx hasil generate.
- `route:cache` sengaja tidak dipakai — `routes/web.php` berisi closure.
- Reverse proxy TLS (nginx traefik/dll) di depan `:8000` (app) dan `:8080`
  (reverb wss) tetap tanggung jawab infra kantor.

## Variabel CI/CD GitLab (Settings → CI/CD → Variables)

| Variabel | Contoh | Catatan |
|----------|--------|---------|
| `DEPLOY_HOST` | `10.0.0.5` | Server produksi |
| `DEPLOY_USER` | `deploy` | User SSH |
| `DEPLOY_SSH_KEY` | (private key) | Masked + protected |
| `DEPLOY_DIR` | `/opt/simpro` | Berisi compose + .env |
| `VITE_REVERB_HOST` | `app.domain.com` | Di-bake ke JS saat build |
| `VITE_REVERB_PORT` | `443` | Port publik websocket |
| `VITE_REVERB_SCHEME` | `wss` | `wss` bila di balik TLS |
| `VITE_APP_NAME` | `SIMPRO` | Opsional |
| `VITE_REVERB_APP_KEY` | sama dgn `REVERB_APP_KEY` | Opsional |

Pipeline: `test` berjalan di MR & main; `build` di main; `deploy` manual di main.

## Dev lokal (DB di container)

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build
# app: http://localhost:8000 — postgres di localhost:5432
```

## Jalankan test via Docker

```bash
docker build --target test -t simpro:test .
# gagal build = gagal test; output test terlihat di log build
```
