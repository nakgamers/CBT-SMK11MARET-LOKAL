# CBT — Aplikasi Ujian Pilihan Ganda

Ujian online pilihan ganda untuk satu sekolah: ratusan siswa login dengan
kartu (NIS + token), bank soal bisa diisi manual atau diimport dari Excel,
dan setiap ujian punya rentang waktu (belum dimulai / berlangsung / berakhir).

Dibangun dengan CodeIgniter 4.7 + MySQL. Tanpa framework CSS/JS dan tanpa
build step: satu file `public/assets/css/app.css` dan JS vanilla, supaya lab
ujian yang offline tidak pernah kehilangan tampilan karena CDN.

---

## Menjalankan

Dua cara, keduanya sudah diuji:

```bash
cd C:/laragon/www/cbt
bash serve-cbt.sh            # http://localhost:8145 (+ alamat IP LAN dicetak)
```

atau lewat Laragon/Apache yang sudah berjalan:
`http://localhost:1103/cbt/public/` — baseURL mendeteksi subfolder sendiri,
jadi tautan dan aset tetap benar tanpa mengubah konfigurasi.

Login awal:

| Peran | Alamat | Kredensial |
|---|---|---|
| Admin | `/admin/login` | `admin` / `admin123` |
| Siswa | `/login` | NIS + token dari kartu |

Ganti password admin: `php spark cbt:admin admin <password-baru> "Nama Admin"`.

### Pasang dari nol

```bash
composer install
cp env .env                        # lalu sesuaikan bagian DATABASE
php spark migrate                  # buat 7 tabel
php spark cbt:admin admin rahasia123 "Administrator Sekolah"
php spark cbt:seed                 # opsional: data contoh + 3 ujian demo
```

`.env` yang penting:

```
database.default.database = cbt
cbt.namaSekolah  = 'SMK NEGERI 1 CONTOH'
cbt.namaAplikasi = 'CBT Ujian Online'
# cbt.alamatLogin = 'http://192.168.1.10:8145/login'   # dicetak di kartu siswa
```

`app.baseURL` sengaja dibiarkan kosong: baseURL mengikuti host yang diakses,
jadi membuka aplikasi lewat `http://192.168.1.10:8145` langsung menghasilkan
tautan dan kartu yang memakai IP itu. Untuk hosting publik, isi `app.baseURL`
dan daftarkan domainnya di `$allowedHostnames` (`app/Config/App.php`).

---

## Alur pemakaian

1. **Siswa** — `/admin/siswa`: import Excel (`nis, nama, kelas, jk`) atau
   tambah manual. Token 6 karakter dibuat otomatis, tanpa karakter kembar
   (tidak ada `0/O`, `1/I/L`, `2/Z`, `5/S`, `8/B`).
2. **Kartu login** — `/admin/siswa/kartu`: lembar siap cetak, bisa difilter
   per kelas. Import ulang NIS yang sama **tidak** mengubah token, sehingga
   kartu yang sudah dibagikan tetap sah.
3. **Bank soal** — `/admin/bank` lalu *Kelola Soal*: tambah manual (dengan
   gambar opsional) atau import Excel
   (`pertanyaan, opsi_a..opsi_e, kunci, bobot`). **Kelima opsi A–E wajib diisi**
   dan kunci harus salah satu A–E; baris yang tidak memenuhi ditolak dan
   alasannya ditampilkan per nomor baris.
4. **Ujian** — `/admin/ujian`: pilih bank, kelas peserta (`*` = semua, atau
   daftar dipisah koma), jumlah soal (0 = semua), durasi, rentang waktu,
   acak soal/opsi, token ujian opsional.
5. **Siswa mengerjakan** — login → daftar ujian → *Mulai*. Jawaban tersimpan
   otomatis setiap klik; menutup browser tidak menghilangkan jawaban.
6. **Nilai** — `/admin/ujian/hasil/{id}`: rekap + statistik, export Excel,
   dan tombol *Reset* per siswa bila harus mengulang.
7. **Analisis butir** — dari halaman nilai, tombol *Analisis Butir*: per soal
   terlihat berapa peserta benar/salah/kosong, tingkat kesukaran
   (mudah ≥70% / sedang 30–69% / sulit <30%), dan sebaran pilihan A–E sehingga
   pengecoh yang tidak bekerja langsung kelihatan. Diurutkan dari tersulit.
8. **Jawaban per siswa** — tombol *Jawaban* pada baris nilai: daftar per nomor
   berisi jawaban siswa, kunci, badge Benar/Salah/Kosong, teks kelima opsi
   (kunci hijau, pilihan siswa bercincin), plus ringkasan waktu dan poin bobot.

### Bagaimana nilai dihitung

`AttemptModel::finalisasi()` adalah satu-satunya tempat skor dihitung, dipakai
baik submit manual maupun auto-submit saat waktu habis. Untuk setiap soal dalam
urutan attempt, jawaban dibandingkan dengan kunci lalu **flag `benar` (1/0)
ditulis ke baris `answers`** — inilah yang membuat analisis butir dan halaman
jawaban bisa dihitung ulang tanpa menebak. Skor = poin bobot yang diperoleh /
total bobot × 100, dibulatkan 2 desimal. Soal yang tidak dijawab tetap menambah
total bobot (dianggap salah), tapi `benar`-nya dibiarkan NULL supaya bisa
dibedakan dari jawaban salah.

### Aturan waktu

Status dihitung dari `mulai_at`/`selesai_at` di satu tempat
(`ExamModel::statusWaktu()`), lalu dipakai controller maupun view:

- **belum** — tombol mati, akses langsung ke URL ujian ditolak.
- **berlangsung** — boleh dikerjakan.
- **lewat** — ditolak; attempt yang masih terbuka dinilai otomatis.
- **nonaktif** — `aktif = 0`.

Deadline peserta = `min(waktu mulai kerja + durasi, selesai_at)`, jadi siswa
yang masuk 10 menit sebelum ujian ditutup tidak bisa melewati jam tutup.
Timer di browser berjalan dari *sisa detik* yang dikirim server dan
disinkronkan ulang pada setiap autosave — mengubah jam komputer tidak
menambah waktu, dan waktu habis memicu penilaian di sisi server.

---

## Mengakses dari HP

Dua pilihan, tergantung HP-nya satu Wi-Fi dengan laptop atau tidak.

**Satu jaringan Wi-Fi** — cukup pakai IP laptop, tanpa alat tambahan:
`bash serve-cbt.sh` mencetak alamat LAN-nya (mis. `http://192.168.0.168:8145`).
Kalau tidak bisa dibuka, izinkan port itu di Windows Firewall.

**Jaringan berbeda (kuota HP, di luar rumah)** — Cloudflare Tunnel:

```bash
bash tunnel-cbt.sh              # tunnel ke php spark serve (8145)
bash tunnel-cbt.sh 1103 /cbt/public   # tunnel ke Laragon/Apache
```

Skrip memeriksa origin hidup lebih dulu (tunnel yang menyala di atas origin mati
hanya menghasilkan 502), lalu mencetak URL `https://xxx.trycloudflare.com` dan
menunggu sampai URL itu benar-benar membalas 200 sebelum bilang siap. Ctrl+C
untuk menutup. Butuh `cloudflared` (sudah ada di
`C:/Program Files (x86)/cloudflared/`).

URL quick tunnel bersifat acak dan berubah setiap dijalankan. Untuk URL tetap,
perlu akun Cloudflare: `cloudflared tunnel login` lalu named tunnel.

Yang membuat ini bekerja: `App::__construct()` membaca `X-Forwarded-Proto`
sehingga baseURL menjadi `https://` saat di belakang tunnel. Tanpa itu, halaman
tampil di https tapi aset dan action form dirender `http://` lalu diblokir
browser sebagai mixed content — gejalanya halaman tampak "HTML gundul" tanpa
CSS. Header itu hanya dipercaya bila permintaan datang dari 127.0.0.1, supaya
tidak bisa dipalsukan dari luar.

Verifikasi menyeluruh lewat URL publik:

```bash
bash dev-uji-tunnel.sh https://xxx.trycloudflare.com 2024001 <TOKEN>
```

Memeriksa aset, login siswa, lembar kerja, autosave AJAX, semua halaman admin,
dan import Excel — semuanya lewat internet, bukan localhost.

**Sebelum dipakai ujian sungguhan lewat tunnel:**

1. Ganti password admin: `php spark cbt:admin admin <password-baru>`.
2. Set `CI_ENVIRONMENT = production` di `.env` (mematikan jejak debug apa pun).
3. Hapus ujian contoh (`Contoh: …`) agar siswa tidak bingung.

URL trycloudflare bersifat publik: siapa pun yang memilikinya bisa membuka
halaman login. Tutup tunnel setelah selesai menguji.

### Debug Toolbar tidak ikut keluar lewat tunnel

Meski `.env` masih `development`, akses dari luar tidak mendapat jejak debug apa
pun. Ini disengaja dan berlapis tiga:

- `App\Filters\LocalToolbar` menggantikan filter `toolbar` bawaan dan melewatkan
  injeksi bila permintaan membawa header proxy (`X-Forwarded-*`, `CF-*`).
  `REMOTE_ADDR` tidak bisa dipakai membedakan, karena cloudflared menghubungi
  origin dari 127.0.0.1 juga.
- `app/Config/Events.php` tidak memanggil `service('toolbar')->respond()` untuk
  permintaan lewat proxy. Ini perlu terpisah karena endpoint `?debugbar` dan
  `?debugbar_time` dilayani pada event `pre_system`, sebelum filter berjalan —
  membungkus filter saja tidak menutupnya, dan tanpa langkah ini endpoint itu
  masih menyajikan 112 KB berisi query SQL dan isi sesi ke publik.
- Kolektor `Views` dimatikan di `app/Config/Toolbar.php`. Kolektor itu
  menyuntikkan komentar `<!-- DEBUG-VIEW START app/Views/... -->` ke setiap
  keluaran view dan komentar tersebut tetap terkirim walau toolbar mati,
  sehingga struktur direktori view bocor.

Efek sampingnya sekaligus menyenangkan: log cloudflared tidak lagi dipenuhi
`Incoming request ended abruptly: context canceled` dari polling toolbar.

Verifikasi:

```bash
curl -s "$U/login"  | grep -c 'debugbar\|DEBUG-VIEW'          # 0
curl -s "$U/?debugbar" | wc -c                                 # halaman biasa, bukan skrip
curl -s http://localhost:8145/login | grep -c debugbar_loader  # 1 (lokal tetap utuh)
```

---

## Verifikasi

Server harus berjalan lebih dulu (`bash serve-cbt.sh`).

```bash
php spark cbt:test        # 101 assertion HTTP end-to-end (server harus jalan)
php spark cbt:test --url http://localhost:1103/cbt/public   # via Laragon
php spark cbt:loadtest --siswa 500 --soal 300
```

`cbt:test` membuat fixturenya sendiri (bank, 5 soal berkunci A–E, 3 ujian dengan
status berbeda, 1 siswa), menjalankan alur ujian penuh lewat HTTP nyata, lalu
menghapus semuanya di blok `finally`. Yang diperiksa: proteksi rute, login
kartu, ketiga status waktu, autosave (termasuk penolakan pilihan/soal tidak
valid), resume, penilaian berikut flag benar/salah per jawaban, auto-submit saat
waktu habis, semua halaman admin termasuk analisis butir dan detail jawaban,
serta import Excel/CSV sungguhan berikut baris cacat yang harus ditolak.

Hasil terakhir pada 500 siswa / 300 soal: rekap nilai 500 peserta 1,49 s,
analisis 300 butir 610 ms, lembar kerja 50 soal 638 ms, penilaian ±69 ms per
attempt.

Pemeriksaan tampilan (butuh Chrome + Node):

```bash
bash dev-snapshot.sh 2024001 <TOKEN>                 # simpan HTML tiap halaman ke /tmp/cbtshots
node dev-audit.mjs "$(cygpath -m /tmp/cbtshots)"     # overflow horizontal di 360-1440px
node dev-shot.mjs <file.html> out.png 390 844 1      # screenshot pada lebar tertentu
node dev-measure.mjs <file.html> 390 844 ".topbar"   # geometri elemen (x, right, overflow)
node dev-contrast.mjs <file.html> 1440 ".muted"      # rasio kontras WCAG teks vs latar
```

Kenapa lewat CDP dan bukan `chrome --headless --window-size`: di Windows, jendela
headless punya lebar minimum ±500px, sehingga `--window-size=390` merender pada
500px lalu memotong PNG-nya jadi 390px — hasilnya tampak seolah ada overflow
padahal tidak. `Emulation.setDeviceMetricsOverride` memberi viewport 390px asli.

---

## Struktur

```
app/
  Commands/     CbtAdmin, CbtSeed, CbtTest, CbtLoadTest
  Controllers/  Auth, Student, Exam + Admin/{Auth,Dashboard,Students,Banks,Questions,Exams}
  Filters/      AdminAuth, StudentAuth (studentAuth balas 401 JSON untuk AJAX)
  Helpers/      cbt_helper.php  (cbt_sekolah, cbt_url_login, tgl_id, badge_status)
  Libraries/    Sheet.php       (baca/tulis xlsx & csv)
  Models/       Admin, Student, Bank, Question, Exam, Attempt, Answer
  Views/        layout/{admin,student,auth} + admin/* + siswa/*
public/assets/css/app.css
```

Tabel: `admins, students, banks, questions, exams, attempts, answers`.
`attempts` unik per `(exam_id, student_id)` sehingga satu siswa tidak bisa
punya dua attempt untuk ujian yang sama; hapus ujian/siswa akan menghapus
jawabannya lewat FK `CASCADE`. Kolom `answers.benar` menyimpan hasil penilaian
per butir (1 benar, 0 salah, NULL belum dijawab) dan menjadi sumber angka pada
analisis butir — bukan dihitung ulang dari kunci setiap kali halaman dibuka.

Masa sesi dan masa token CSRF disetel 4 jam (`app/Config/Session.php` dan
`Security.php`). Bawaan CI4 dua jam, dan itu membuat siswa ter-logout atau
autosave-nya ditolak 403 di tengah ujian berdurasi 120 menit.

---

## Catatan keamanan

- CSRF aktif global (`app/Config/Filters.php`), termasuk untuk autosave AJAX.
  Tokennya disimpan di **sesi**, bukan cookie: `csrfProtection = 'session'`.
  Mode cookie (bawaan CI4) menyetel cookie sekali dengan masa berlaku tetap dan
  tidak memperpanjangnya, sehingga halaman yang lama dibiarkan terbuka —
  misalnya form import soal sementara guru menyiapkan berkas Excel — ditolak
  saat disubmit walau admin masih terlihat login. Dengan mode sesi, token hidup
  selama sesi, dan sesi diperpanjang tiap kali session id diregenerasi
  (`Session::$timeToUpdate`, 300 detik). Ini diverifikasi lewat HTTP di
  `cbt:test`, bukan dengan membaca konfigurasi.
- Gagal CSRF tidak lagi memunculkan layar exception. `Security::$redirect = true`
  mengembalikan pengguna ke halaman sebelumnya, dan `app/Language/id/Security.php`
  menggantikan "The action you requested is not allowed." dengan instruksi:
  muat ulang halaman lalu ulangi unggah.
- Semua rute `siswa/*` dan `admin/*` dijaga filter; diverifikasi lewat HTTP
  di `cbt:test`, bukan dengan membaca konfigurasi.
- Token kartu disimpan plaintext **secara sengaja** — harus bisa dicetak.
  Karena itu lembar kartu adalah dokumen ujian: bagikan per kelas dan reset
  token setelah ujian (`Token` pada baris siswa) bila perlu.
- Kunci jawaban tidak pernah dikirim ke browser selama ujian berlangsung;
  hanya muncul di halaman hasil, dan hanya jika *tampilkan hasil* dicentang.

## Belum dibuat

Diserahkan sampai benar-benar dibutuhkan: soal esai/menjodohkan, bobot
negatif, QR code di kartu, live monitoring peserta, multi-sekolah, dan
daya-beda (discrimination index) pada analisis butir — indeks itu baru
bermakna kalau pesertanya banyak, sementara sekarang halaman analisis sudah
memperingatkan sendiri bila peserta selesai masih di bawah 10.
Untuk ujian >1000 peserta serentak, jalankan di Apache/Nginx + PHP-FPM,
bukan `php spark serve` (server bawaan single-threaded).
