<?php

namespace App\Commands;

use App\Models\AttemptModel;
use App\Models\BankModel;
use App\Models\ExamModel;
use App\Models\QuestionModel;
use App\Models\StudentModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Uji skala: ratusan siswa + ratusan soal, lalu ukur waktu respons halaman
 * yang paling berat (lembar kerja ujian & rekap nilai admin).
 *
 * php spark cbt:loadtest [--siswa 400] [--soal 200] [--url http://localhost:8145]
 */
class CbtLoadTest extends BaseCommand
{
    protected $group       = 'CBT';
    protected $name        = 'cbt:loadtest';
    protected $description = 'Uji skala: ratusan siswa & soal, ukur waktu respons halaman berat.';
    protected $usage       = 'cbt:loadtest [--siswa 400] [--soal 200] [--url http://localhost:8145]';
    protected $options     = [
        '--siswa' => 'Jumlah siswa uji (default 400)',
        '--soal'  => 'Jumlah soal uji (default 200)',
        '--url'   => 'Base URL server berjalan',
    ];

    public function run(array $params)
    {
        helper('cbt');
        $nSiswa = (int) (CLI::getOption('siswa') ?: 400);
        $nSoal  = (int) (CLI::getOption('soal') ?: 200);
        $base   = rtrim((string) (CLI::getOption('url') ?: 'http://localhost:8145'), '/');

        $db     = db_connect();
        $bankId = null;
        $examId = null;

        try {
            // ------------------------------------------------------- fixture
            $t = microtime(true);
            $bankId = (int) model(BankModel::class)->insert([
                'nama' => '__LOAD BANK__', 'mapel' => 'Uji Beban',
            ], true);

            $soal = [];
            for ($i = 1; $i <= $nSoal; $i++) {
                $soal[] = [
                    'bank_id'    => $bankId,
                    'teks'       => "__LOAD__ Soal nomor {$i}: manakah jawaban yang benar dari pilihan berikut?",
                    'opsi_a'     => 'pilihan A nomor ' . $i,
                    'opsi_b'     => 'pilihan B nomor ' . $i,
                    'opsi_c'     => 'pilihan C nomor ' . $i,
                    'opsi_d'     => 'pilihan D nomor ' . $i,
                    'opsi_e'     => 'pilihan E nomor ' . $i,
                    'kunci'      => ['A', 'B', 'C', 'D', 'E'][$i % 5],
                    'bobot'      => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
            $db->table('questions')->insertBatch($soal);

            $siswa = [];
            for ($i = 1; $i <= $nSiswa; $i++) {
                $siswa[] = [
                    'nis'        => '__LOAD' . str_pad((string) $i, 5, '0', STR_PAD_LEFT) . '__',
                    'nama'       => 'Siswa Beban ' . $i,
                    'kelas'      => '__LOADKLS' . (1 + intdiv($i, 36)) . '__',
                    'jk'         => $i % 2 ? 'L' : 'P',
                    'token'      => StudentModel::generateToken(),
                    'aktif'      => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
            $db->table('students')->insertBatch($siswa);
            CLI::write(sprintf('Fixture: %d siswa + %d soal dalam %.2fs', $nSiswa, $nSoal, microtime(true) - $t), 'cyan');

            $examId = (int) model(ExamModel::class)->insert([
                'nama'            => '__LOAD UJIAN__',
                'bank_id'         => $bankId,
                'kelas'           => '*',
                'jumlah_soal'     => min(50, $nSoal),
                'durasi_menit'    => 90,
                'mulai_at'        => date('Y-m-d H:i:s', time() - 600),
                'selesai_at'      => date('Y-m-d H:i:s', time() + 7200),
                'acak_soal'       => 1,
                'acak_opsi'       => 1,
                'tampilkan_hasil' => 1,
                'aktif'           => 1,
            ], true);

            // ------------------------------------- attempt untuk semua siswa
            $t   = microtime(true);
            $ids = model(QuestionModel::class)->idsUntukUjian($bankId, min(50, $nSoal), true);
            $rows = [];
            $sAll = $db->table('students')->select('id')->like('nis', '__LOAD')->get()->getResultArray();
            foreach ($sAll as $s) {
                $rows[] = [
                    'exam_id'     => $examId,
                    'student_id'  => (int) $s['id'],
                    'urutan'      => json_encode($ids),
                    'started_at'  => date('Y-m-d H:i:s'),
                    'deadline_at' => date('Y-m-d H:i:s', time() + 5400),
                    'status'      => 'berlangsung',
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ];
            }
            $db->table('attempts')->insertBatch($rows);
            CLI::write(sprintf('%d attempt dibuat dalam %.2fs', count($rows), microtime(true) - $t), 'cyan');

            // jawaban: 20 soal terjawab untuk 100 siswa pertama
            $t = microtime(true);
            $att = $db->table('attempts')->select('id')->where('exam_id', $examId)->limit(100)->get()->getResultArray();
            $jaw = [];
            foreach ($att as $a) {
                foreach (array_slice($ids, 0, 20) as $qid) {
                    $jaw[] = [
                        'attempt_id'  => (int) $a['id'],
                        'question_id' => $qid,
                        'jawaban'     => ['A', 'B', 'C', 'D', 'E'][random_int(0, 4)],
                        'ragu'        => 0,
                        'created_at'  => date('Y-m-d H:i:s'),
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ];
                }
            }
            $db->table('answers')->insertBatch($jaw);
            CLI::write(sprintf('%d jawaban dimasukkan dalam %.2fs', count($jaw), microtime(true) - $t), 'cyan');

            // -------------------------------- ukur waktu penilaian (finalisasi)
            CLI::newLine();
            CLI::write('Waktu proses (server-side):', 'yellow');
            $am = model(AttemptModel::class);
            $t  = microtime(true);
            foreach (array_slice($att, 0, 50) as $a) {
                $am->finalisasi((int) $a['id']);
            }
            $ms = (microtime(true) - $t) * 1000;
            $this->lap('finalisasi 50 attempt', $ms, 15000);
            $this->lap('  → per attempt', $ms / 50, 300);

            $t = microtime(true);
            model(AttemptModel::class)->hasilUjian($examId);
            $this->lap("query rekap nilai {$nSiswa} peserta", (microtime(true) - $t) * 1000, 1500);

            $t = microtime(true);
            model(StudentModel::class)->daftarKelas();
            $this->lap('query daftar kelas', (microtime(true) - $t) * 1000, 500);

            // ------------------------------------------- ukur waktu HTTP nyata
            CLI::newLine();
            CLI::write("Waktu respons HTTP ({$base}):", 'yellow');

            $siswaRow = $db->table('students')
                ->select('students.nis, students.token')
                ->join('attempts', 'attempts.student_id = students.id')
                ->where('attempts.exam_id', $examId)
                ->where('attempts.status', 'berlangsung')   // yang sudah dinilai akan dialihkan ke /hasil
                ->limit(1)
                ->get()->getRowArray();
            $jar      = tempnam(sys_get_temp_dir(), 'load');
            $h        = $this->csrf($this->http('GET', $base . '/login', null, $jar));
            $this->http('POST', $base . '/login', [
                $h[0] => $h[1], 'nis' => $siswaRow['nis'], 'token' => $siswaRow['token'],
            ], $jar);

            $this->lapHttp('GET /siswa (dashboard)', $base . '/siswa', $jar, 1500);
            $kerja = $this->lapHttp('GET /siswa/kerjakan (50 soal)', $base . '/siswa/kerjakan/' . $examId, $jar, 2500);
            $nRender = substr_count($kerja, 'class="q-card q-page');
            $this->lap("  → soal benar-benar dirender: {$nRender}", $nRender >= 50 ? 0 : 99999, 1);

            $jarA = tempnam(sys_get_temp_dir(), 'loadA');
            $h    = $this->csrf($this->http('GET', $base . '/admin/login', null, $jarA));
            $this->http('POST', $base . '/admin/login', [$h[0] => $h[1], 'username' => 'admin', 'password' => 'admin123'], $jarA);
            $this->lapHttp('GET /admin (dashboard)', $base . '/admin', $jarA, 2000);
            $this->lapHttp('GET /admin/siswa (paginasi 50)', $base . '/admin/siswa', $jarA, 2000);
            $this->lapHttp("GET /admin/ujian/hasil ({$nSiswa} baris)", $base . '/admin/ujian/hasil/' . $examId, $jarA, 4000);
            // analisis butir memindai semua jawaban peserta selesai + urutan tiap attempt
            $this->lapHttp("GET /admin/ujian/analisis ({$nSoal} butir)", $base . '/admin/ujian/analisis/' . $examId, $jarA, 5000);
            $this->lapHttp('GET /admin/siswa/kartu (semua kartu)', $base . '/admin/siswa/kartu', $jarA, 5000);

            @unlink($jar);
            @unlink($jarA);
        } finally {
            CLI::newLine();
            $t = microtime(true);
            if ($examId) {
                // answers & attempts ikut terhapus lewat FK CASCADE
                $db->table('exams')->where('id', $examId)->delete();
            }
            $db->table('students')->like('nis', '__LOAD')->delete();
            if ($bankId) {
                $db->table('questions')->where('bank_id', $bankId)->delete();
                $db->table('banks')->where('id', $bankId)->delete();
            }
            CLI::write(sprintf('Fixture dibersihkan (%.2fs).', microtime(true) - $t), 'cyan');
        }

        CLI::newLine();
        CLI::write($this->fail === 0 ? 'SEMUA DI BAWAH AMBANG BATAS' : "{$this->fail} pengukuran melewati ambang batas", $this->fail ? 'red' : 'green');

        return $this->fail ? EXIT_ERROR : EXIT_SUCCESS;
    }

    private int $fail = 0;

    private function lap(string $label, float $ms, float $batas): void
    {
        $ok = $ms <= $batas;
        if (! $ok) {
            $this->fail++;
        }
        CLI::write(sprintf('  %s %-42s %8.1f ms (batas %.0f)', $ok ? 'OK  ' : 'LAMBAT', $label, $ms, $batas), $ok ? 'green' : 'red');
    }

    private function lapHttp(string $label, string $url, string $jar, float $batas): string
    {
        $t    = microtime(true);
        $body = $this->http('GET', $url, null, $jar);
        $ms   = (microtime(true) - $t) * 1000;
        $this->lap($label . ' [' . number_format(strlen($body) / 1024, 0) . ' KB]', $ms, $batas);

        return $body;
    }

    private function http(string $method, string $url, ?array $data, string $jar): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_COOKIEJAR      => $jar,
            CURLOPT_COOKIEFILE     => $jar,
        ] + ($data !== null ? [CURLOPT_POSTFIELDS => http_build_query($data)] : []));
        $body = (string) curl_exec($ch);
        curl_close($ch);

        return $body;
    }

    /** @return array{0:string,1:string} */
    private function csrf(string $html): array
    {
        return preg_match('/name="([^"]*csrf[^"]*)"\s+value="([^"]+)"/i', $html, $m)
            ? [$m[1], $m[2]]
            : ['csrf_test_name', ''];
    }
}
