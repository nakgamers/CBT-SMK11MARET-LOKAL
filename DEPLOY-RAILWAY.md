# Deploy CBT SMK 11 Maret ke Railway.app

Prasyarat: akun GitHub + akun Railway (daftar gratis lewat GitHub).
Repo ini sudah berisi `Dockerfile` + `docker/` — Railway otomatis memakainya,
jadi tidak perlu buildpack apa pun.

## Arsitektur di Railway

```
Browser/HP ──https── Railway edge ──:8080── [ container CBT ]
                                          nginx -> php-fpm -> CI4
                                          MySQL service (terpisah)
```

- `Dockerfile` — PHP 8.3-FPM + nginx + supervisor, satu image.
- `docker/entrypoint.sh` — `php spark migrate` otomatis tiap container hidup.
- `docker/nginx.conf` — rewrite ala CI4, root `public/`, listen 8080.
- `docker/supervisord.conf` — jaga nginx & php-fpm tetap hidup, log ke stdout.

## 1. Push ke GitHub

```bash
cd C:/laragon/www/cbt
git init
git add -A
git commit -m "CBT SMK 11 Maret siap deploy"
git branch -M main
git remote add origin https://github.com/<userkamu>/cbt.git
git push -u origin main
```

`.gitignore` sudah mengecualikan `.env`, `vendor/`, `writable/*` — kredensial
lokal tidak ikut ke GitHub. `composer.lock` justru HARUS ikut (build deterministik).

## 2. Buat project Railway dari repo

1. railway.app -> **New Project** -> **Deploy from GitHub repo** -> pilih `cbt`.
2. Railway mendeteksi `Dockerfile` dan memakainya. Kalau tidak:
   Service -> **Settings -> Dockerfile** -> aktifkan.
3. Tunggu build pertama (±3-5 menit: composer install di dalam image).

## 3. Tambah MySQL

1. Di project yang sama: **New -> Database -> MySQL** (plugin resmi Railway).
2. Buka service **cbt** -> tab **Variables** -> isi:

| Key | Value (lewat dropdown reference) |
|---|---|
| `database.default.hostname` | `${{MySQL.DB_HOST}}` |
| `database.default.database` | `${{MySQL.DB_NAME}}` |
| `database.default.username` | `${{MySQL.DB_USER}}` |
| `database.default.password` | `${{MySQL.DB_PASSWORD}}` |
| `database.default.DBDriver` | `MySQLi` |
| `database.default.port`      | `${{MySQL.DB_PORT}}` |
| `CI_ENVIRONMENT`             | `production` |
| `TZ`                         | `Asia/Jakarta` |

PENTING `TZ`: jadwal ujian dihitung dari jam server. Tanpa `Asia/Jakarta`,
Railway memakai UTC dan semua rentang waktu meleset 7 jam.

Variabel `database.default.*` dibaca langsung oleh `app/Config/Database.php`
CI4 (konvensi `database.<grup>.<key>`), jadi tidak perlu `.env`.

## 4. URL publik

Service cbt -> **Settings -> Networking -> Generate Domain**.
Port: **8080** (sesuai `EXPOSE` Dockerfile). Dapat
`https://cbt-xxxx.up.railway.app`.

## 5. Verifikasi deploy

1. Tab **Deployments -> View Logs** harus menampilkan:
   - `[entrypoint]` / output `php spark migrate` -> `SUCCESS`
   - nginx + php-fpm hidup tanpa error
2. Buka `https://cbt-xxxx.up.railway.app/login` -> halaman login siswa
   dengan logo SMK 11 Maret harus muncul.

## 6. Buat admin + isi data

Railway tidak menyediakan terminal interaktif untuk `php spark`, jadi:

**Opsi A (paling mudah): jalankan dari laptop sendiri via Railway CLI.**
```bash
npm i -g @railway/cli
railway login
railway link                     # pilih project cbt
railway run php spark cbt:admin admin <password-kuat> "Administrator"
```
`railway run` menyuntikkan variabel produksi ke perintah lokal — butuh PHP
8.2+ di laptop (Laragon sudah punya), dan laptop harus bisa mengakses DB
Railway (bisa; MySQL Railway menerima koneksi publik).

**Opsi B: impor data lewat dashboard.**
Setelah admin ada, semua sisanya (impor siswa Excel, bank soal, ujian)
cukup dari http://.../admin/login — tidak perlu terminal lagi.

## 7. Uji dari HP

`https://cbt-xxxx.up.railway.app/login` -> NIS + token dari kartu.
Cetak kartu dari admin -> Siswa & Kartu -> Kartu.

## Catatan penting

- **Biaya**: Railway bukan free-tier penuh — kredit percobaan habis lalu
  langganan minimal ~$5/bln (termasuk service MySQL). Untuk 1 sekolah yang
  sudah punya server lab, hosting lokal tetap lebih murah; Railway berguna
  kalau siswa harus ujian dari luar jaringan sekolah.
- **Upload gambar soal** tersimpan di `writable/uploads` dalam container dan
  HILANG saat redeploy. Kalau dipakai serius: Settings -> **Volumes**,
  mount path `/app/writable` (volume persist antar deploy).
- **Backup DB**: service MySQL -> tab **Backups** (otomatis harian di plan
  berbayar), atau `mysqldump` berkala dari laptop via `railway run`.
- **Batas body upload** sudah dinaikkan ke 25 MB di nginx (Excel + gambar).
- **Update kode**: `git push` -> Railway rebuild + redeploy otomatis.
  Migrate jalan lagi saat container baru hidup (idempoten, aman).
- **Uji lokal sebelum push** (butuh Docker Desktop):
  ```bash
  docker build -t cbt .
  docker run -p 8080:8080 -e CI_ENVIRONMENT=production cbt
  # lalu buka http://localhost:8080 — tanpa DB, halaman error DB adalah hasil
  # yang benar; yang diuji di sini jalur nginx->php-fpm-nya.
  ```
