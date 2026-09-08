#!/bin/sh
# Jalan sekali saat container hidup, SEBELUM nginx+php-fpm dinyalakan.
# Migrate idempoten: aman walau container restart / scale berulang.
# GAGAL migrate tidak boleh menghentikan web — log error tetap terlihat,
# dan admin bisa perbaiki variabel DB lalu restart service.
cd /app
php spark migrate --no-ansi || echo "[entrypoint] migrate gagal — cek variabel database"
exec "$@"
