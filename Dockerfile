# syntax=docker/dockerfile:1

# ============================================================================
# SIMPRO — Pengawasan Pembangunan Kapal
# Multi-stage image: base -> vendor -> assets -> test -> production
#
# Build produksi:
#   docker build \
#     --build-arg VITE_REVERB_APP_KEY=<key> \
#     --build-arg VITE_REVERB_HOST=<host-websocket-publik> \
#     --build-arg VITE_REVERB_PORT=443 \
#     --build-arg VITE_REVERB_SCHEME=https \
#     -t simpro:latest .
#
# Jalankan test (gagal build = gagal test):
#   docker build --target test -t simpro:test .
# ============================================================================

# ----------------------------------------------------------------------------
# BASE — PHP-FPM runtime + nginx + supervisor + poppler-utils (render PDF NC)
# ----------------------------------------------------------------------------
FROM php:8.5-fpm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        # pdfinfo/pdftoppm/pdftotext/pdfseparate/pdfunite dipakai
        # PdfPageService + PdfPengesahanService (render & gabung PDF)
        poppler-utils \
        # soffice headless: konversi DOCX→PDF lampiran rantai laporan
        # (DocxToPdfService — di Windows ada fallback Word COM)
        libreoffice-writer \
        fontconfig \
        fonts-dejavu-core \
        fonts-liberation \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pdo_sqlite \
        intl \
        zip \
        gd \
        bcmath \
        exif \
        pcntl \
        sockets \
    # opcache tidak di-install: di PHP 8.5 sudah built-in ke engine (bukan shared ext),
    # cukup diaktifkan via docker/php.ini (opcache.enable=1)
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

ENV SOFFICE_BINARY=/usr/bin/soffice \
    SAL_USE_VCLPLUGIN=svp

RUN <<'SHELL'
set -eu
fc-cache -f
test -x "$SOFFICE_BINARY"
su -s /bin/sh www-data <<'CONVERT'
set -eu
work="$(mktemp -d)"
trap 'rm -r "$work"' EXIT
printf '%s\n' '{\rtf1\ansi\deff0 {\fonttbl {\f0 Liberation Sans;}}\f0\fs20 SIMPRO PDF conversion check}' > "$work/smoke.rtf"
/usr/bin/soffice "-env:UserInstallation=file://$work/profile" --headless --norestore --convert-to pdf:writer_pdf_Export --outdir "$work" "$work/smoke.rtf" >/dev/null
test -s "$work/smoke.pdf"
pdfinfo "$work/smoke.pdf" >/dev/null
CONVERT
SHELL

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/nginx.conf /etc/nginx/conf.d/app.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf

WORKDIR /var/www/html

# ----------------------------------------------------------------------------
# VENDOR — composer dependencies (produksi, tanpa dev)
# ----------------------------------------------------------------------------
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-scripts \
        --no-progress

# ----------------------------------------------------------------------------
# ASSETS — Vite/Tailwind build. VITE_* di-bake saat build (bukan runtime).
# ----------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

ARG VITE_APP_NAME=""
ARG VITE_REVERB_APP_KEY=""
ARG VITE_REVERB_HOST="localhost"
ARG VITE_REVERB_PORT="8080"
ARG VITE_REVERB_SCHEME="http"

ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
# tailwind.config.js content[] ikut memindai app/Enums/*.php
COPY app/Enums ./app/Enums

RUN npm run build

# ----------------------------------------------------------------------------
# TEST — dijalankan CI: composer install (dengan dev deps) + php artisan test.
# APP_KEY dummy diperlukan karena HasEncryptedRouteKey memakai Crypt.
# ----------------------------------------------------------------------------
FROM base AS test

ENV APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" \
    APP_ENV=testing

COPY composer.json composer.lock ./
RUN composer install \
        --prefer-dist \
        --no-interaction \
        --no-scripts \
        --no-progress

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && php artisan test

# ----------------------------------------------------------------------------
# PRODUCTION — image final (nginx + php-fpm via supervisord, port 80)
# ----------------------------------------------------------------------------
FROM base AS production

# Copy hanya path yang dibutuhkan runtime — .env/tests/vendor/node_modules
# tidak ikut (selain lewat .dockerignore juga dihindari di sini).
COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data resources ./resources
COPY --chown=www-data:www-data routes ./routes
COPY --chown=www-data:www-data storage ./storage
COPY --chown=www-data:www-data artisan composer.json composer.lock .env.example ./

COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN rm -rf public/storage \
    && mkdir -p \
        storage/app \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && su -s /bin/sh www-data -c "php artisan package:discover --ansi"

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["app-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/app.conf"]
