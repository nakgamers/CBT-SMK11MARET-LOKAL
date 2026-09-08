# Deploy CBT SMK 11 Maret ke Railway.app
#
# Railway menjalankan file ini bila project-nya di-set ke Dockerfile
# (default: Railway mendeteksi Dockerfile di root repo dan memakainya
# di atas Nixpacks). Hasilnya deterministik: PHP-FPM + nginx + supervisor.

FROM php:8.3-fpm-bookworm

# nginx + supervisor untuk web server, lib* untuk ekstensi PHP yang dipakai
# CI4 & PhpSpreadsheet (zip untuk xlsx, gd untuk gambar, intl untuk locale).
RUN apt-get update && apt-get install -y --no-install-recommends \
      nginx supervisor \
      libzip-dev libpng-dev libjpeg-dev libonig-dev libicu-dev \
 && docker-php-ext-install mysqli zip gd intl opcache \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# php-fpm: pakai socket unix (dipakai nginx.conf) dan JANGAN bersihkan
# environment — CI4 membaca kredensial DB dari variabel Railway di worker.
# Catatan: image resmi php:fpm memakai `listen = 9000` (TCP), jadi pola
# di bawah sengaja longgar agar cocok ke semua variasi default.
RUN sed -i 's|^listen = .*|listen = /run/php/php-fpm.sock|' \
      /usr/local/etc/php-fpm.d/www.conf \
 && sed -i 's|^;\?clear_env = .*|clear_env = no|' /usr/local/etc/php-fpm.d/www.conf \
 && mkdir -p /run/php

# layer dependency dulu: ubah kode tidak memicu ulang composer install
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev \
 && mkdir -p writable/cache writable/logs writable/session writable/uploads \
 && chown -R www-data:www-data writable

COPY docker/nginx.conf      /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/cbt.conf
COPY docker/entrypoint.sh   /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Railway mengarahkan domain publik ke port yang diumumkan di sini
# (Settings -> Networking -> Service Port = 8080).
EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
