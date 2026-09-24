#!/bin/sh
# Jalan sekali saat container hidup, SEBELUM nginx+php-fpm dinyalakan.
# Migrate idempoten: aman walau container restart / scale berulang.
# GAGAL migrate tidak boleh menghentikan web — log error tetap terlihat,
# dan admin bisa perbaiki variabel DB lalu restart service.
cd /app
# Volume persisten (jika terpasang) di-mount sebagai root -> pastikan www-data bisa tulis
mkdir -p writable/uploads/selfie public/uploads/soal public/uploads/soal-inline \
 && chown -R www-data:www-data writable/uploads public/uploads 2>/dev/null || true
php spark migrate --no-ansi || echo "[entrypoint] migrate gagal — cek variabel database"
exec "$@"
