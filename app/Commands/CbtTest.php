<?php

namespace App\Commands;

use App\Models\AnswerModel;
use App\Models\AttemptModel;
use App\Models\ExamModel;
use App\Models\QuestionModel;
use App\Models\StudentModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji end-to-end lewat HTTP nyata terhadap `php spark serve`.
 * Membuat fixture sendiri (siswa + bank + soal + 3 ujian), menjalankan
 * alur ujian penuh sebagai siswa, lalu menghapus semua fixture di finally.
 *
 * php spark cbt:test [--url http://localhost:8145]
 */
class CbtTest extends BaseCommand
{
    protected $group       = 'CBT';
    protected $name        = 'cbt:test';
    protected $description = 'Smoke test end-to-end: auth, rentang waktu ujian, autosave, penilaian, dashboard admin.';
    protected $usage       = 'cbt:test [--url http://localhost:8145]';
    protected $options     = ['--url' => 'Base URL server yang sedang berjalan'];

    private string $base;
    private int $pass = 0;
    private int $fail = 0;
    private string $jarSiswa;
    private string $jarAdmin;

    public function run(array $params)
    {
        helper('cbt');
        $this->base     = rtrim((string) (CLI::getOption('url') ?: 'http://localhost:8145'), '/');
        $tmp            = sys_get_temp_dir();
        $this->jarSiswa = tempnam($tmp, 'cbtS');
        $this->jarAdmin = tempnam($tmp, 'cbtA');

        CLI::write("Target: {$this->base}", 'yellow');
        CLI::newLine();

        $db  = db_connect();
        $ids = ['bank' => null, 'soal' => [], 'exam' => [], 'siswa' => null];

        try {
            // ---------------------------------------------------------- fixture
            $bankModel = model(\App\Models\BankModel::class);
            $ids['bank'] = (int) $bankModel->insert([
                'nama'  => '__TEST BANK__',
                'mapel' => 'Uji Otomatis',
            ], true);

            $qModel = model(QuestionModel::class);
            $kunci  = ['A', 'B', 'C', 'D', 'E'];
            foreach ($kunci as $n => $k) {
                $ids['soal'][] = (int) $qModel->insert([
                    'bank_id' => $ids['bank'],
                    'teks'    => 'Soal uji nomor ' . ($n + 1),
                    'opsi_a'  => 'pilihan A',
                    'opsi_b'  => 'pilihan B',
                    'opsi_c'  => 'pilihan C',
                    'opsi_d'  => 'pilihan D',
                    'opsi_e'  => 'pilihan E',
                    'kunci'   => $k,
                    'bobot'   => 1,
                ], true);
            }

            $examModel = model(ExamModel::class);
            $mk        = static fn (string $nama, string $mulai, string $selesai, int $durasi) => [
                'nama'            => $nama,
                'bank_id'         => 0,
                'kelas'           => '__TESTKLS__',
                'jumlah_soal'     => 0,
                'durasi_menit'    => $durasi,
                'mulai_at'        => date('Y-m-d H:i:s', strtotime($mulai)),
                'selesai_at'      => date('Y-m-d H:i:s', strtotime($selesai)),
                'acak_soal'       => 0,
                'acak_opsi'       => 0,
                'tampilkan_hasil' => 1,
                'aktif'           => 1,
            ];

            foreach ([
                'aktif' => $mk('__TEST AKTIF__', '-10 minutes', '+2 hours', 60),
                'belum' => $mk('__TEST BELUM__', '+2 hours', '+3 hours', 60),
                'lewat' => $mk('__TEST LEWAT__', '-3 hours', '-1 hour', 60),
            ] as $key => $row) {
                $row['bank_id']     = $ids['bank'];
                $ids['exam'][$key]  = (int) $examModel->insert($row, true);
            }

            $token = StudentModel::generateToken();
            $ids['siswa'] = (int) model(StudentModel::class)->insert([
                'nis'   => '__TEST999__',
                'nama'  => 'Siswa Uji Otomatis',
                'kelas' => '__TESTKLS__',
                'jk'    => 'L',
                'token' => $token,
                'aktif' => 1,
            ], true);

            CLI::write('Fixture dibuat: 1 bank, 5 soal (kunci A-E), 3 ujian, 1 siswa.', 'cyan');
            CLI::newLine();

            // ------------------------------------------------------- A. proteksi
            CLI::write('A. Proteksi rute', 'yellow');
            foreach (['siswa', 'siswa/kerjakan/1', 'admin', 'admin/siswa', 'admin/ujian'] as $u) {
                $r = $this->req('GET', $u, null, null);
                $this->ok(in_array($r['code'], [302, 303], true), "GET /{$u} tanpa login ditolak ({$r['code']})");
            }
            $r = $this->req('POST', 'siswa/jawab/1', ['question_id' => 1], null, true);
            $this->ok($r['code'] === 403, "POST AJAX tanpa CSRF token -> 403 (dapat {$r['code']})");

            // Form biasa (non-AJAX) tanpa token: harus dialihkan dengan pesan
            // berbahasa Indonesia, bukan halaman exception 403.
            $r = $this->req('POST', 'admin/soal/1/import', ['x' => '1'], null);
            $this->ok(
                in_array($r['code'], [302, 303], true),
                "POST form tanpa CSRF -> dialihkan, bukan 403 (dapat {$r['code']})"
            );

            // CSRF valid tapi belum login: filter studentAuth harus balas 401 JSON
            $jarKosong = tempnam(sys_get_temp_dir(), 'cbtX');
            $c         = $this->csrfFor('login', $jarKosong);
            $r         = $this->post('siswa/jawab/1', ['question_id' => 1, $c[0] => $c[1]], $jarKosong, true);
            $this->ok($r['code'] === 401, "POST AJAX ber-CSRF tanpa sesi -> 401 (dapat {$r['code']})");
            @unlink($jarKosong);

            // Token CSRF harus tersimpan di SESI, bukan cookie: cookie punya masa
            // berlaku tetap yang tidak diperpanjang, sehingga halaman yang lama
            // dibiarkan terbuka gagal saat submit (kasus import soal).
            $this->ok(
                config(\Config\Security::class)->csrfProtection === 'session',
                'CSRF disimpan di sesi (token ikut umur sesi login)'
            );
            $jarCk = tempnam(sys_get_temp_dir(), 'cbtC');
            $this->get('admin/login', $jarCk);
            $isiJar = (string) @file_get_contents($jarCk);
            $this->ok(
                ! str_contains($isiJar, 'csrf_cookie_name'),
                'Tidak ada cookie csrf_cookie_name yang bisa kedaluwarsa sendiri'
            );
            @unlink($jarCk);

            // -------------------------------------------------- B. login siswa
            CLI::newLine();
            CLI::write('B. Login siswa (kartu NIS + token)', 'yellow');

            $this->postForm('login', ['nis' => '__TEST999__', 'token' => 'SALAH1'], $this->jarSiswa);
            $this->ok(str_contains($this->get('login', $this->jarSiswa)['body'], 'salah'), 'Token salah ditolak dengan pesan error');

            $r = $this->postForm('login', ['nis' => '__TEST999__', 'token' => $token], $this->jarSiswa);
            $this->ok(in_array($r['code'], [302, 303], true), "Login benar -> redirect ({$r['code']})");

            $dash = $this->get('siswa', $this->jarSiswa);
            $this->ok($dash['code'] === 200, "Dashboard siswa 200 (dapat {$dash['code']})");
            $this->ok(str_contains($dash['body'], 'Siswa Uji Otomatis'), 'Nama siswa tampil di dashboard');
            $this->ok(str_contains($dash['body'], '__TEST AKTIF__'), 'Ujian aktif muncul di daftar');

            // ----------------------------------------------- C. rentang waktu
            CLI::newLine();
            CLI::write('C. Rentang waktu ujian', 'yellow');
            $this->ok(str_contains($dash['body'], 'Belum dibuka'), 'Ujian belum mulai: tombol "Belum dibuka"');
            $this->ok(str_contains($dash['body'], 'Ditutup'), 'Ujian lewat: tombol "Ditutup"');

            foreach (['belum' => 'belum dimulai', 'lewat' => 'sudah berakhir'] as $key => $frasa) {
                $this->get('siswa/kerjakan/' . $ids['exam'][$key], $this->jarSiswa);
                $body = $this->get('siswa', $this->jarSiswa)['body'];
                $this->ok(stripos($body, $frasa) !== false, "Akses ujian '{$key}' ditolak: \"{$frasa}\"");
            }
            $this->ok(
                model(AttemptModel::class)->where('exam_id', $ids['exam']['belum'])->countAllResults() === 0,
                'Ujian di luar jadwal tidak membuat attempt'
            );

            // ----------------------------------------------- D. kerjakan ujian
            CLI::newLine();
            CLI::write('D. Mengerjakan ujian', 'yellow');
            $examId = $ids['exam']['aktif'];

            $kerja = $this->get('siswa/kerjakan/' . $examId, $this->jarSiswa);
            $this->ok($kerja['code'] === 200, "Lembar kerja 200 (dapat {$kerja['code']})");
            $this->ok(substr_count($kerja['body'], 'class="q-card q-page') === 5, 'Ke-5 soal dirender');
            $this->ok(substr_count($kerja['body'], 'name="q' . $ids['soal'][0] . '"') === 5, 'Soal pertama punya 5 opsi (A-E)');
            $this->ok(str_contains($kerja['body'], 'id="timer"'), 'Timer ada di halaman');

            $attempt = model(AttemptModel::class)->findAktif($examId, $ids['siswa']);
            $this->ok($attempt !== null, 'Attempt dibuat saat mulai');
            if ($attempt === null) {
                throw new \RuntimeException('Attempt tidak terbentuk — uji lanjutan dibatalkan.');
            }
            $this->ok($attempt['status'] === 'berlangsung', 'Status attempt = berlangsung');
            $sisaDetik = strtotime($attempt['deadline_at']) - strtotime($attempt['started_at']);
            $this->ok(abs($sisaDetik - 3600) <= 5, "Deadline = mulai + 60 menit ({$sisaDetik}s)");

            $csrf = $this->csrf($kerja['body']);
            $this->ok($csrf !== null, 'CSRF token tersedia untuk autosave');

            // 3 benar (termasuk opsi E), 1 sengaja salah, soal ke-4 (kunci D) dibiarkan kosong
            $jawaban = [
                $ids['soal'][0] => 'A',   // benar
                $ids['soal'][1] => 'B',   // benar
                $ids['soal'][2] => 'A',   // salah (kunci C)
                $ids['soal'][4] => 'E',   // benar — memastikan opsi E benar-benar dipakai
            ];
            foreach ($jawaban as $qid => $pilih) {
                $r = $this->post('siswa/jawab/' . $examId, [
                    'question_id' => $qid,
                    'jawaban'     => $pilih,
                    $csrf[0]      => $csrf[1],
                ], $this->jarSiswa, true);
                $this->ok($r['code'] === 200, "Autosave soal {$qid} -> 200 (dapat {$r['code']})");
            }

            $tersimpan = model(AnswerModel::class)->petaAttempt((int) $attempt['id']);
            $this->ok(count($tersimpan) === 4, 'Tepat 4 jawaban tersimpan di DB (dapat ' . count($tersimpan) . ')');

            // autosave nakal harus ditolak
            $r = $this->post('siswa/jawab/' . $examId, ['question_id' => $ids['soal'][0], 'jawaban' => 'Z', $csrf[0] => $csrf[1]], $this->jarSiswa, true);
            $this->ok($r['code'] === 422, "Pilihan di luar A-E ditolak 422 (dapat {$r['code']})");
            $r = $this->post('siswa/jawab/' . $examId, ['question_id' => 999999, 'jawaban' => 'A', $csrf[0] => $csrf[1]], $this->jarSiswa, true);
            $this->ok($r['code'] === 422, "Soal bukan milik attempt ditolak 422 (dapat {$r['code']})");

            // resume: buka ulang, jawaban harus masih terpilih
            $ulang = $this->get('siswa/kerjakan/' . $examId, $this->jarSiswa);
            $this->ok(substr_count($ulang['body'], 'class="opt sel"') === 4, 'Resume: 4 opsi masih tersimpan/terpilih');
            $this->ok(
                model(AttemptModel::class)->where('exam_id', $examId)->where('student_id', $ids['siswa'])->countAllResults() === 1,
                'Buka ulang tidak menggandakan attempt'
            );

            // --------------------------------------------------- E. penilaian
            CLI::newLine();
            CLI::write('E. Kumpulkan & penilaian', 'yellow');
            $r = $this->post('siswa/selesai/' . $examId, [$csrf[0] => $csrf[1]], $this->jarSiswa);
            $this->ok(in_array($r['code'], [302, 303], true), "Submit -> redirect ({$r['code']})");

            $sesudah = model(AttemptModel::class)->find($attempt['id']);
            $this->ok($sesudah['status'] === 'selesai', 'Status attempt jadi selesai');
            $this->ok((int) $sesudah['benar'] === 3, 'Benar = 3 (dapat ' . (int) $sesudah['benar'] . ')');
            $this->ok((int) $sesudah['salah'] === 1, 'Salah = 1 (dapat ' . (int) $sesudah['salah'] . ')');
            $this->ok((int) $sesudah['kosong'] === 1, 'Kosong = 1 (dapat ' . (int) $sesudah['kosong'] . ')');
            $this->ok(abs((float) $sesudah['skor'] - 60.0) < 0.01, 'Skor = 60.00 (3/5 bobot) (dapat ' . $sesudah['skor'] . ')');

            // flag benar/salah per jawaban harus tertulis — dipakai analisis butir
            $flag = model(AnswerModel::class)->petaAttempt((int) $attempt['id']);
            $this->ok(
                (int) ($flag[$ids['soal'][0]]['benar'] ?? -1) === 1,
                'Flag benar=1 tersimpan pada jawaban yang tepat'
            );
            $this->ok(
                (int) ($flag[$ids['soal'][2]]['benar'] ?? -1) === 0,
                'Flag benar=0 tersimpan pada jawaban yang salah'
            );
            $this->ok(
                (int) ($flag[$ids['soal'][4]]['benar'] ?? -1) === 1,
                'Opsi E dinilai benar (kunci E)'
            );

            $hasil = $this->get('siswa/hasil/' . $examId, $this->jarSiswa);
            $this->ok($hasil['code'] === 200, "Halaman hasil 200 (dapat {$hasil['code']})");
            $this->ok(str_contains($hasil['body'], '60,00'), 'Nilai 60,00 tampil di halaman hasil');
            // fitur pembahasan sudah dihapus: siswa hanya melihat nilai
            $this->ok(
                ! str_contains($hasil['body'], 'Pembahasan Jawaban'),
                'Halaman hasil TIDAK memuat pembahasan jawaban'
            );
            $this->ok(
                ! str_contains($hasil['body'], '&larr; kunci'),
                'Kunci jawaban tidak dibocorkan di halaman hasil siswa'
            );

            // tampilkan_hasil = 0 -> URL hasil harus ditolak server, bukan cuma
            // tombolnya disembunyikan di dashboard
            model(ExamModel::class)->update($examId, ['tampilkan_hasil' => 0]);
            $tutup = $this->get('siswa/hasil/' . $examId, $this->jarSiswa);
            $this->ok(
                str_contains($tutup['location'], 'siswa'),
                'tampilkan_hasil=0 -> halaman hasil dialihkan (Location: ' . ($tutup['location'] ?: '-') . ')'
            );
            $this->ok(
                ! str_contains($tutup['body'], '60,00'),
                'tampilkan_hasil=0 -> nilai tidak ikut terkirim'
            );
            model(ExamModel::class)->update($examId, ['tampilkan_hasil' => 1]);

            // ujian yang sudah selesai tidak bisa dikerjakan lagi
            $r = $this->post('siswa/jawab/' . $examId, ['question_id' => $ids['soal'][3], 'jawaban' => 'D', $csrf[0] => $csrf[1]], $this->jarSiswa, true);
            $this->ok($r['code'] === 409, "Autosave setelah selesai ditolak 409 (dapat {$r['code']})");
            $r = $this->get('siswa/kerjakan/' . $examId, $this->jarSiswa);
            $this->ok(
                str_contains($r['location'], 'siswa/hasil/' . $examId),
                'Buka lembar kerja setelah selesai -> dialihkan ke hasil (Location: ' . ($r['location'] ?: '-') . ')'
            );

            // --------------------------------------------- E2. anti-cheat
            CLI::newLine();
            CLI::write('E2. Anti-cheat', 'yellow');
            $this->ujiAntiCheat($examId, $ids['bank'], $ids['soal'][0]);
            $this->ujiGangguanKoneksi($examId, $ids['soal'][0]);

            // ------------------------------------------------ F. auto submit
            CLI::newLine();
            CLI::write('F. Waktu habis = auto-submit', 'yellow');
            $am = model(AttemptModel::class);
            $am->update($attempt['id'], [
                'status'       => 'berlangsung',
                'submitted_at' => null,
                'deadline_at'  => date('Y-m-d H:i:s', time() - 60),
            ]);
            $r = $this->get('siswa/kerjakan/' . $examId, $this->jarSiswa);
            $lagi = $am->find($attempt['id']);
            $this->ok($lagi['status'] === 'selesai', 'Deadline lewat -> attempt dinilai otomatis');
            $this->ok(
                str_contains($r['location'], 'siswa/hasil/' . $examId),
                'Siswa diarahkan ke halaman hasil (Location: ' . ($r['location'] ?: '-') . ')'
            );
            $this->ok(
                stripos($this->get('siswa/hasil/' . $examId, $this->jarSiswa)['body'], 'otomatis dikumpulkan') !== false,
                'Pesan "otomatis dikumpulkan" tampil di halaman hasil'
            );

            // -------------------------------------------------- G. admin panel
            CLI::newLine();
            CLI::write('G. Dashboard admin', 'yellow');
            $adm = model(\App\Models\AdminModel::class)->first();
            if (! $adm) {
                CLI::write('  ! tidak ada admin; jalankan php spark cbt:admin', 'yellow');
            } else {
                $r = $this->postForm('admin/login', ['username' => $adm['username'], 'password' => 'admin123'], $this->jarAdmin);
                $masuk = in_array($r['code'], [302, 303], true) && $this->get('admin', $this->jarAdmin)['code'] === 200;

                if (! $masuk) {
                    CLI::write("  ! login admin '{$adm['username']}' gagal (password bukan admin123?) — bagian admin dilewati", 'yellow');
                } else {
                    foreach ([
                        'admin'                                                   => 'Dashboard',
                        'admin/siswa'                                             => 'Kartu Login',
                        'admin/bank'                                              => 'Bank Soal',
                        'admin/soal/' . $ids['bank']                              => 'Soal uji nomor 1',
                        'admin/ujian'                                             => '__TEST AKTIF__',
                        'admin/ujian/hasil/' . $examId                            => 'Siswa Uji Otomatis',
                        'admin/ujian/analisis/' . $examId                         => 'Analisis Butir',
                        'admin/ujian/jawaban/' . $examId . '/' . $ids['siswa']    => 'Jawaban per Nomor',
                        'admin/siswa/kartu'                                       => 'Kartu Login Ujian',
                    ] as $url => $harus) {
                        $g = $this->get($url, $this->jarAdmin);
                        $this->ok($g['code'] === 200 && str_contains($g['body'], $harus), "GET /{$url} 200 & memuat \"{$harus}\" (code {$g['code']})");
                    }

                    $g = $this->get('admin/ujian/hasil/' . $examId, $this->jarAdmin);
                    $this->ok(str_contains($g['body'], '60,00'), 'Nilai peserta tampil di rekap admin');

                    // analisis butir: 5 soal, tiap butir 1 peserta
                    $g = $this->get('admin/ujian/analisis/' . $examId, $this->jarAdmin);
                    $this->ok(substr_count($g['body'], 'class="sebaran"') === 5, 'Analisis butir memuat 5 butir soal');
                    $this->ok(str_contains($g['body'], 'Tingkat Kesukaran'), 'Kolom tingkat kesukaran ada');
                    $this->ok(
                        substr_count($g['body'], 'title="Opsi ') === 25,
                        'Setiap butir menampilkan sebaran 5 opsi A-E (dapat ' . substr_count($g['body'], 'title="Opsi ') . '/25)'
                    );
                    // 1 soal dibiarkan kosong -> kolom KOSONG harus mencatatnya
                    $this->ok(
                        preg_match('/class="center dim">1</', $g['body']) === 1,
                        'Butir yang dikosongkan tercatat di kolom Kosong'
                    );

                    // detail jawaban per siswa: 3 benar, 1 salah, 1 kosong
                    $g = $this->get('admin/ujian/jawaban/' . $examId . '/' . $ids['siswa'], $this->jarAdmin);
                    $this->ok(substr_count($g['body'], '&#10003; Benar') === 3, 'Detail jawaban: 3 baris Benar (dapat ' . substr_count($g['body'], '&#10003; Benar') . ')');
                    $this->ok(substr_count($g['body'], '&#10007; Salah') === 1, 'Detail jawaban: 1 baris Salah');
                    $this->ok(substr_count($g['body'], '&#9711; Kosong') === 1, 'Detail jawaban: 1 baris Kosong');
                    // teks opsi ikut ditampilkan supaya kunci bisa diaudit tanpa buka bank soal
                    $this->ok(substr_count($g['body'], 'class="opsi-mini"') === 5, 'Detail jawaban: teks opsi A-E ditampilkan per soal');
                    // catatan: kelas bisa "o kunci" atau "o kunci dipilih" (kunci = jawaban siswa),
                    // jadi hitung berdasarkan awalan, bukan string persis
                    $this->ok(
                        substr_count($g['body'], 'class="o kunci') === 5,
                        'Opsi kunci ditandai di kelima soal (dapat ' . substr_count($g['body'], 'class="o kunci') . ')'
                    );
                    $this->ok(str_contains($g['body'], 'Skor mentah'), 'Ringkasan skor mentah (poin bobot) ada');

                    // siswa yang belum mengerjakan tidak boleh memunculkan halaman jawaban
                    $g = $this->get('admin/ujian/jawaban/' . $ids['exam']['belum'] . '/' . $ids['siswa'], $this->jarAdmin);
                    $this->ok(
                        in_array($g['code'], [302, 303], true),
                        'Jawaban siswa tanpa attempt -> dialihkan (dapat ' . $g['code'] . ')'
                    );

                    foreach (['admin/siswa/template', 'admin/soal/template', 'admin/ujian/export/' . $examId] as $u) {
                        $g = $this->get($u, $this->jarAdmin);
                        $xlsx = str_starts_with($g['body'], "PK\x03\x04");
                        $this->ok($g['code'] === 200 && $xlsx, "GET /{$u} mengirim file xlsx asli (code {$g['code']})");
                    }

                    // ------------------------------------- import excel nyata
                    CLI::newLine();
                    CLI::write('G2. Import Excel (file xlsx sungguhan)', 'yellow');
                    $this->ujiImport($ids['bank']);
                }
            }

            // -------------------------------------------------- H. status waktu
            CLI::newLine();
            CLI::write('H. Unit: ExamModel::statusWaktu & untukKelas', 'yellow');

            // Regression: halaman selfie pertama (tanpa selfie tersimpan) tidak
            // boleh melempar TypeError karena tombol Ambil Ulang belum dirender.
            $selfieView = file_get_contents(APPPATH . 'Views/siswa/selfie.php');
            $this->ok(
                is_string($selfieView)
                && str_contains($selfieView, "const btnUlang = document.getElementById('btnUlang');")
                && str_contains($selfieView, "if (btnUlang) {"),
                'Selfie tanpa foto tetap memasang handler Kirim (btnUlang null aman)'
            );
            $now = strtotime('2026-06-15 10:00:00');
            $ex  = ['mulai_at' => '2026-06-15 09:00:00', 'selesai_at' => '2026-06-15 11:00:00', 'aktif' => 1];
            $this->ok(ExamModel::statusWaktu($ex, $now) === 'berlangsung', 'Di dalam rentang -> berlangsung');
            $this->ok(ExamModel::statusWaktu($ex, strtotime('2026-06-15 08:59:59')) === 'belum', 'Sebelum mulai -> belum');
            $this->ok(ExamModel::statusWaktu($ex, strtotime('2026-06-15 11:00:01')) === 'lewat', 'Setelah selesai -> lewat');
            $this->ok(ExamModel::statusWaktu(['aktif' => 0] + $ex, $now) === 'nonaktif', 'aktif=0 -> nonaktif');
            $this->ok(ExamModel::untukKelas(['kelas' => '*'], 'XII RPL 1'), "kelas '*' menerima semua");
            $this->ok(ExamModel::untukKelas(['kelas' => 'XII RPL 1,XII RPL 2'], 'XII RPL 2'), 'Daftar kelas koma cocok');
            $this->ok(! ExamModel::untukKelas(['kelas' => 'XII RPL 1'], 'XII TKJ 1'), 'Kelas tidak cocok ditolak');
        } finally {
            // ------------------------------------------------------- bersihkan
            if ($ids['siswa']) {
                $db->table('attempts')->where('student_id', $ids['siswa'])->delete();
                $db->table('students')->where('id', $ids['siswa'])->delete();
            }
            foreach ($ids['exam'] as $eid) {
                $db->table('exams')->where('id', $eid)->delete();
            }
            if ($ids['bank']) {
                $db->table('questions')->where('bank_id', $ids['bank'])->delete();
                $db->table('banks')->where('id', $ids['bank'])->delete();
            }
            @unlink($this->jarSiswa);
            @unlink($this->jarAdmin);
            CLI::newLine();
            CLI::write('Fixture dibersihkan.', 'cyan');
        }

        CLI::newLine();
        CLI::write("PASS: {$this->pass}   FAIL: {$this->fail}", $this->fail > 0 ? 'red' : 'green');

        return $this->fail > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }

    // ------------------------------------------------------------------ util

    /** Verifikasi aturan anti-cheat pada database nyata dengan fixture yang selalu dibersihkan. */
    private function ujiAntiCheat(int $examId, int $bankId, int $questionId): void
    {
        $db = db_connect();
        $studentId = null;
        $attemptId = null;
        try {
            $studentId = (int) model(StudentModel::class)->insert([
                'nis' => '__ANTICHEAT_' . random_int(1000, 9999),
                'nama' => 'Siswa Uji Anti Cheat',
                'kelas' => '__TESTKLS__',
                'jk' => 'L',
                'token' => StudentModel::generateToken(),
                'aktif' => 1,
            ], true);
            $attemptId = (int) model(AttemptModel::class)->insert([
                'exam_id' => $examId,
                'student_id' => $studentId,
                'urutan' => json_encode([$questionId]),
                'started_at' => date('Y-m-d H:i:s'),
                'deadline_at' => date('Y-m-d H:i:s', time() + 3600),
                'status' => 'berlangsung',
                'ip' => '127.0.0.1',
            ], true);

            $answers = model(AnswerModel::class);
            $attempts = model(AttemptModel::class);
            $answers->simpan($attemptId, $questionId, 'A');
            $first = $attempts->catatPelanggaran($attemptId);
            $this->ok($first !== null && $first['pelanggaran'] === 1 && ! $first['dihentikan'], 'Pelanggaran pertama tercatat 1/3');
            $this->ok($answers->petaAttempt($attemptId) === [], 'Pelanggaran pertama menghapus semua jawaban');

            $second = $attempts->catatPelanggaran($attemptId);
            $this->ok($second !== null && $second['pelanggaran'] === 2 && ! $second['dihentikan'], 'Pelanggaran kedua tercatat 2/3');
            $third = $attempts->catatPelanggaran($attemptId);
            $final = $attempts->find($attemptId);
            $this->ok($third !== null && $third['pelanggaran'] === 3 && $third['dihentikan'], 'Pelanggaran ketiga menghentikan ujian');
            $this->ok($final['status'] === 'selesai' && $answers->petaAttempt($attemptId) === [], 'Pelanggaran ketiga finalisasi dengan jawaban kosong');
        } finally {
            if ($attemptId) {
                $db->table('answers')->where('attempt_id', $attemptId)->delete();
                $db->table('attempts')->where('id', $attemptId)->delete();
            }
            if ($studentId) {
                $db->table('students')->where('id', $studentId)->delete();
            }
        }
    }

    /** Verifikasi lima gangguan koneksi: jawaban tetap ada hingga attempt selesai. */
    private function ujiGangguanKoneksi(int $examId, int $questionId): void
    {
        $db = db_connect();
        $studentId = null;
        $attemptId = null;
        try {
            $studentId = (int) model(StudentModel::class)->insert([
                'nis' => '__CONNECTION_' . random_int(1000, 9999), 'nama' => 'Siswa Uji Koneksi',
                'kelas' => '__TESTKLS__', 'jk' => 'L', 'token' => StudentModel::generateToken(), 'aktif' => 1,
            ], true);
            $attemptId = (int) model(AttemptModel::class)->insert([
                'exam_id' => $examId, 'student_id' => $studentId, 'urutan' => json_encode([$questionId]),
                'started_at' => date('Y-m-d H:i:s'), 'deadline_at' => date('Y-m-d H:i:s', time() + 3600),
                'status' => 'berlangsung', 'ip' => '127.0.0.1',
            ], true);
            $answers = model(AnswerModel::class);
            $attempts = model(AttemptModel::class);
            $answers->simpan($attemptId, $questionId, 'A');
            for ($i = 1; $i <= 4; $i++) {
                $hasil = $attempts->catatGangguanKoneksi($attemptId);
                $this->ok($hasil !== null && $hasil['gangguan'] === $i && ! $hasil['dihentikan'], "Gangguan koneksi {$i}/5 tercatat");
            }
            $this->ok($answers->petaAttempt($attemptId) !== [], 'Gangguan koneksi tidak menghapus jawaban tersimpan');
            $hasil = $attempts->catatGangguanKoneksi($attemptId);
            $final = $attempts->find($attemptId);
            $this->ok($hasil !== null && $hasil['gangguan'] === 5 && $hasil['dihentikan'], 'Gangguan koneksi kelima menghentikan ujian');
            $this->ok($final['status'] === 'selesai', 'Gangguan koneksi kelima memfinalisasi attempt');
        } finally {
            if ($attemptId) {
                $db->table('answers')->where('attempt_id', $attemptId)->delete();
                $db->table('attempts')->where('id', $attemptId)->delete();
            }
            if ($studentId) $db->table('students')->where('id', $studentId)->delete();
        }
    }

    /**
     * Buat xlsx sungguhan lalu unggah lewat form import (siswa & soal),
     * termasuk baris cacat yang harus ditolak.
     */
    private function ujiImport(int $bankId): void
    {
        $db  = db_connect();
        $tmp = sys_get_temp_dir();

        // ---------- import siswa
        $fSiswa = $tmp . DIRECTORY_SEPARATOR . 'uji-siswa-' . bin2hex(random_bytes(4)) . '.xlsx';
        $this->tulisXlsx($fSiswa, ['nis', 'nama', 'kelas', 'jk'], [
            ['__IMP001__', 'Import Satu', '__IMPKLS__', 'L'],
            ['__IMP002__', 'Import Dua', '__IMPKLS__', 'P'],
            ['', 'Tanpa NIS', '__IMPKLS__', 'L'],   // harus ditolak
        ]);

        $r = $this->upload('admin/siswa/import', $fSiswa, $this->jarAdmin);
        $this->ok(in_array($r['code'], [302, 303], true), "Upload xlsx siswa -> redirect ({$r['code']})");

        $masuk = $db->table('students')->whereIn('nis', ['__IMP001__', '__IMP002__'])->countAllResults();
        $this->ok($masuk === 2, "2 siswa masuk dari xlsx (dapat {$masuk})");
        $tolak = $db->table('students')->where('nama', 'Tanpa NIS')->countAllResults();
        $this->ok($tolak === 0, 'Baris tanpa NIS ditolak, tidak masuk DB');

        $tok = $db->table('students')->where('nis', '__IMP001__')->get()->getRowArray()['token'] ?? '';
        $this->ok($tok === '__IMP001__', "Token import disamakan dengan NIS (dapat '{$tok}')");

        // import ulang: data diperbarui, token TIDAK berubah
        $this->tulisXlsx($fSiswa, ['nis', 'nama', 'kelas', 'jk'], [
            ['__IMP001__', 'Import Satu Revisi', '__IMPKLS2__', 'L'],
        ]);
        $this->upload('admin/siswa/import', $fSiswa, $this->jarAdmin);
        $row = $db->table('students')->where('nis', '__IMP001__')->get()->getRowArray();
        $this->ok($row['nama'] === 'Import Satu Revisi', 'Import ulang memperbarui nama');
        $this->ok($row['kelas'] === '__IMPKLS2__', 'Import ulang memperbarui kelas');
        $this->ok($row['token'] === $tok, 'Token TIDAK berubah saat import ulang (kartu tetap sah)');
        $jml = $db->table('students')->where('nis', '__IMP001__')->countAllResults();
        $this->ok($jml === 1, "Tidak ada duplikat NIS (dapat {$jml} baris)");

        // ---------- import soal (opsi A-E kini wajib semua)
        $fSoal = $tmp . DIRECTORY_SEPARATOR . 'uji-soal-' . bin2hex(random_bytes(4)) . '.xlsx';
        $this->tulisXlsx($fSoal, ['pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci', 'bobot'], [
            ['__IMPSOAL__ satu', 'a1', 'b1', 'c1', 'd1', 'e1', 'C', 2],
            ['__IMPSOAL__ dua', 'a2', 'b2', 'c2', 'd2', 'e2', 'E', 1],      // kunci E harus diterima
            ['__IMPSOAL__ opsi-e-kosong', 'a3', 'b3', 'c3', 'd3', '', 'A', 1],  // E kosong: ditolak
            ['__IMPSOAL__ kunci-ngawur', 'a4', 'b4', 'c4', 'd4', 'e4', 'X', 1], // kunci bukan A-E: ditolak
        ]);

        $r = $this->upload('admin/soal/' . $bankId . '/import', $fSoal, $this->jarAdmin);
        $this->ok(in_array($r['code'], [302, 303], true), "Upload xlsx soal -> redirect ({$r['code']})");

        $ok2 = $db->table('questions')->like('teks', '__IMPSOAL__')->countAllResults();
        $this->ok($ok2 === 2, "Hanya 2 soal valid yang masuk (dapat {$ok2})");
        $bobot = $db->table('questions')->where('teks', '__IMPSOAL__ satu')->get()->getRowArray()['bobot'] ?? 0;
        $this->ok((int) $bobot === 2, "Kolom bobot terbaca dari xlsx (dapat {$bobot})");
        $kunciE = $db->table('questions')->where('teks', '__IMPSOAL__ dua')->get()->getRowArray();
        $this->ok(($kunciE['kunci'] ?? '') === 'E', 'Soal berkunci E berhasil diimport');
        $this->ok(($kunciE['opsi_e'] ?? '') === 'e2', 'Kolom opsi_e tersimpan dari xlsx');
        $eKosong = $db->table('questions')->where('teks', '__IMPSOAL__ opsi-e-kosong')->countAllResults();
        $this->ok($eKosong === 0, 'Baris dengan opsi E kosong ditolak (A-E wajib)');

        $pesan = $this->get('admin/soal/' . $bankId, $this->jarAdmin)['body'];
        $this->ok(str_contains($pesan, 'kunci') || str_contains($pesan, 'Baris'), 'Halaman menampilkan alasan baris yang ditolak');

        // ---------- csv juga harus jalan
        $fCsv = $tmp . DIRECTORY_SEPARATOR . 'uji-siswa-' . bin2hex(random_bytes(4)) . '.csv';
        file_put_contents($fCsv, "nis,nama,kelas,jk\n__IMPCSV__,Import Csv,__IMPKLS__,P\n");
        $this->upload('admin/siswa/import', $fCsv, $this->jarAdmin);
        $csvMasuk = $db->table('students')->where('nis', '__IMPCSV__')->countAllResults();
        $this->ok($csvMasuk === 1, "Import CSV juga berhasil (dapat {$csvMasuk})");

        // ---------- bersihkan
        $db->table('students')->whereIn('nis', ['__IMP001__', '__IMP002__', '__IMPCSV__'])->delete();
        $db->table('questions')->like('teks', '__IMPSOAL__')->delete();
        foreach ([$fSiswa, $fSoal, $fCsv] as $f) {
            @unlink($f);
        }
    }

    /** @param list<string> $header @param list<list<mixed>> $baris */
    private function tulisXlsx(string $path, array $header, array $baris): void
    {
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->fromArray($header, null, 'A1');
        $sh->fromArray($baris, null, 'A2');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);
    }

    private function upload(string $uri, string $file, string $jar): array
    {
        $c = $this->csrfFor('admin/siswa', $jar);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->base . '/' . ltrim($uri, '/'),
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_COOKIEJAR      => $jar,
            CURLOPT_COOKIEFILE     => $jar,
            CURLOPT_POSTFIELDS     => [
                ($c[0] ?? 'csrf_test_name') => ($c[1] ?? ''),
                'berkas'                    => new \CURLFile($file, 'application/octet-stream', basename($file)),
            ],
        ]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hdrN = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return ['code' => $code, 'body' => substr($body, $hdrN), 'location' => ''];
    }

    private function ok(bool $cond, string $label): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write('  PASS  ' . $label, 'green');

            return;
        }
        $this->fail++;
        CLI::write('  FAIL  ' . $label, 'red');
    }

    private function get(string $uri, ?string $jar): array
    {
        return $this->req('GET', $uri, null, $jar);
    }

    private function post(string $uri, array $data, ?string $jar, bool $ajax = false): array
    {
        return $this->req('POST', $uri, $data, $jar, $ajax);
    }

    /** GET halaman berisi form lalu POST dengan CSRF token miliknya. */
    private function postForm(string $uri, array $data, string $jar): array
    {
        $c = $this->csrfFor($uri, $jar);
        if ($c !== null) {
            $data[$c[0]] = $c[1];
        }

        return $this->post($uri, $data, $jar);
    }

    /** @return array{0:string,1:string}|null */
    private function csrfFor(string $uri, string $jar): ?array
    {
        return $this->csrf($this->get($uri, $jar)['body']);
    }

    /**
     * cURL dengan CUSTOMREQUEST eksplisit — CURLOPT_POST saja membuat GET
     * berikutnya tetap terkirim sebagai POST.
     */
    private function req(string $method, string $uri, ?array $data, ?string $jar, bool $ajax = false): array
    {
        $ch   = curl_init();
        $opts = [
            CURLOPT_URL            => $this->base . '/' . ltrim($uri, '/'),
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $ajax ? ['X-Requested-With: XMLHttpRequest'] : [],
        ];
        if ($jar !== null) {
            $opts[CURLOPT_COOKIEJAR]  = $jar;
            $opts[CURLOPT_COOKIEFILE] = $jar;
        }
        if ($data !== null) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
        }
        curl_setopt_array($ch, $opts);

        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hdrN = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $head = substr($body, 0, $hdrN);
        $body = substr($body, $hdrN);
        $loc  = preg_match('/^Location:\s*(\S+)/mi', $head, $m) ? $m[1] : '';

        return ['code' => $code, 'body' => $body, 'location' => $loc];
    }

    /** @return array{0:string,1:string}|null [nama, hash] */
    private function csrf(string $html): ?array
    {
        if (preg_match('/CSRF = \{ name: "([^"]+)", hash: "([^"]+)"/', $html, $m)) {
            return [$m[1], $m[2]];
        }
        if (preg_match('/name="([^"]*csrf[^"]*)"\s+value="([^"]+)"/i', $html, $m)) {
            return [$m[1], $m[2]];
        }

        return null;
    }
}
