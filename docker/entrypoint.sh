#!/bin/sh
set -e

cd /var/www/html

# Saat container root (app): rapikan ownership volume shared lalu jalankan
# artisan sebagai www-data. worker/reverb/scheduler sudah start sebagai
# www-data (compose `user:`) — `su` ke user yang sama butuh password -> gagal.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

as_app_run() {
    if [ "$(id -u)" = "0" ]; then su -s /bin/sh www-data -c "$1"; else eval "$1"; fi
}

# .env fallback: bila tidak ada .env (mount/env_file), buat dari .env.example
# agar container tetap bisa boot. Produksi tetap WAJIB menyediakan .env/env
# dengan DB_* & SSO_* — fallback ini untuk kemudahan dev/demo saja.
if [ ! -f .env ] && [ -f .env.example ]; then
    echo "[entrypoint] .env tidak ditemukan — dibuat dari .env.example." >&2
    cp .env.example .env
fi

# APP_KEY: generate sekali bila kosong — HasEncryptedRouteKey & Crypt butuh key.
if ! grep -qE '^APP_KEY=base64:[A-Za-z0-9+/=]{20,}' .env 2>/dev/null; then
    as_app_run "php artisan key:generate --force" || true
fi

# CATATAN: route:cache sengaja tidak dipakai — routes/web.php berisi closure.
as_app_run "php artisan storage:link --force" >/dev/null 2>&1 || true
as_app_run "php artisan config:cache"
as_app_run "php artisan view:cache"

# Migrasi opt-in per container start (default aktif di compose).
if [ "${MIGRATE_ON_START:-false}" = "true" ]; then
    as_app_run "php artisan migrate --force"
fi

# Seeder inti opt-in (SEED_ON_START=true): permission, role, user default,
# konfigurasi sistem, cuaca & kelembaban. Semua seeder ini idempotent
# (firstOrCreate) sehingga aman dijalankan tiap boot. Data demo laporan
# TIDAK ikut — jalankan `php artisan db:seed` manual bila perlu.
if [ "${SEED_ON_START:-false}" = "true" ]; then
    for SEEDER in PermissionSeeder RoleSeeder SystemConfigurationSeeder KelembabanSeeder CuacaSeeder UserSeeder; do
        as_app_run "php artisan db:seed --force --class=$SEEDER" || true
    done
fi

exec "$@"
