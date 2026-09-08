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

# image resmi php:fpm TIDAK membawa composer — ambil binary-nya dari
# image composer resmi (build error Railway: "composer: not found").
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# php-fpm: pakai socket unix (dipakai nginx.conf) dan JANGAN bersihkan
# environment — CI4 membaca kredensial DB dari variabel Railway di worker.
# www.conf bawaan image DITIMPA total (sed terbukti rapuh lintas versi image).
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
RUN mkdir -p /run/php

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
