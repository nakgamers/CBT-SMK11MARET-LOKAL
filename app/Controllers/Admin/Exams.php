<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\ResultExport;
use App\Libraries\Sheet;
use App\Models\AnswerModel;
use App\Models\AttemptModel;
use App\Models\BankModel;
use App\Models\ExamModel;
use App\Models\QuestionModel;
use App\Models\StudentModel;

class Exams extends BaseController
{
    public function index()
    {
        $exams = model(ExamModel::class)->withRingkasan();
        $now   = time();
        foreach ($exams as &$e) {
            $e['status_waktu'] = ExamModel::statusWaktu($e, $now);
        }
        unset($e);

        return view('admin/ujian', [
            'title'       => 'Jadwal & Hasil Ujian',
            'exams'       => $exams,
            'banks'       => model(BankModel::class)->withJumlahSoal(),
            'daftarKelas' => model(StudentModel::class)->kelasDenganJumlah(),
        ]);
    }

    public function simpan()
    {
        $model = model(ExamModel::class);
        $id    = (int) $this->request->getPost('id');

        // input datetime-local mengirim "2026-03-01T07:30" -> normalkan ke SQL
        $sql = static function (?string $v): string {
            $v = trim((string) $v);
            if ($v === '') {
                return '';
            }
            $ts = strtotime(str_replace('T', ' ', $v));

            return $ts ? date('Y-m-d H:i:s', $ts) : '';
        };

        /*
         * Kelas peserta kini dikirim sebagai daftar centang: `semua_kelas`
         * (checkbox) atau `kelas[]` (array). Kosong TIDAK boleh diam-diam
         * menjadi '*' — itu membuat ujian terbuka untuk seluruh sekolah.
         */
        if ($this->request->getPost('semua_kelas')) {
            $kelas = '*';
        } else {
            $pilih = $this->request->getPost('kelas');
            $pilih = is_array($pilih) ? $pilih : (array) $pilih;
            $pilih = array_values(array_unique(array_filter(array_map(
                static fn ($v) => trim((string) $v),
                $pilih
            ))));

            if ($pilih === []) {
                return redirect()->back()
                    ->with('error', 'Pilih minimal satu kelas peserta, atau centang "Semua kelas".')
                    ->withInput();
            }

            // hanya terima kelas yang benar-benar ada di data siswa
            $sah   = model(StudentModel::class)->daftarKelas();
            $asing = array_diff($pilih, $sah);
            if ($asing !== []) {
                return redirect()->back()
                    ->with('error', 'Kelas tidak dikenal: ' . implode(', ', $asing))
                    ->withInput();
            }

            $kelas = implode(',', $pilih);
        }

        $data = [
            'nama'            => trim((string) $this->request->getPost('nama')),
            'bank_id'         => (int) $this->request->getPost('bank_id'),
            'kelas'           => $kelas,
            'jumlah_soal'     => max(0, (int) $this->request->getPost('jumlah_soal')),
            'durasi_menit'    => max(1, (int) $this->request->getPost('durasi_menit')),
            'mulai_at'        => $sql($this->request->getPost('mulai_at')),
            'selesai_at'      => $sql($this->request->getPost('selesai_at')),
            'acak_soal'       => $this->request->getPost('acak_soal') ? 1 : 0,
            'acak_opsi'       => $this->request->getPost('acak_opsi') ? 1 : 0,
            'tampilkan_hasil' => $this->request->getPost('tampilkan_hasil') ? 1 : 0,
            'absen_selfie'    => $this->request->getPost('absen_selfie') ? 1 : 0,
            'token'           => strtoupper(trim((string) $this->request->getPost('token'))) ?: null,
            'aktif'           => $this->request->getPost('aktif') ? 1 : 0,
        ];

        if ($data['mulai_at'] !== '' && $data['selesai_at'] !== ''
            && strtotime($data['selesai_at']) <= strtotime($data['mulai_at'])) {
            return redirect()->back()->with('error', 'Waktu selesai harus setelah waktu mulai.')->withInput();
        }

        $tersedia = model(QuestionModel::class)->where('bank_id', $data['bank_id'])->countAllResults();
        if ($data['jumlah_soal'] > $tersedia) {
            return redirect()->back()
                ->with('error', "Bank soal hanya punya {$tersedia} soal, tidak bisa mengambil {$data['jumlah_soal']}.")
                ->withInput();
        }

        $ok = $id ? $model->update($id, $data) : $model->insert($data);
        if ($ok === false) {
            return redirect()->back()->with('error', $model->errors())->withInput();
        }

        return redirect()->to(site_url('admin/ujian'))
            ->with('success', $id ? 'Ujian diperbarui.' : 'Ujian dibuat.');
    }

    public function hapus(int $id)
    {
        model(ExamModel::class)->delete($id);

        return redirect()->back()->with('success', 'Ujian dihapus beserta seluruh hasilnya.');
    }

    public function hasil(int $id)
    {
        $exam = model(ExamModel::class)->withBank($id);
        if (! $exam) {
            return redirect()->to(site_url('admin/ujian'))->with('error', 'Ujian tidak ditemukan.');
        }

        $hasil  = model(AttemptModel::class)->hasilUjian($id);
        $selesai = array_filter($hasil, static fn ($h) => $h['status'] === 'selesai');
        $skor    = array_map(static fn ($h) => (float) $h['skor'], $selesai);

        // siswa yang seharusnya ikut tapi belum ada attempt sama sekali
        $semua  = model(StudentModel::class)->where('aktif', 1)->orderBy('kelas')->orderBy('nama')->findAll();
        $ikut   = array_column($hasil, 'student_id');
        $absen  = array_values(array_filter(
            $semua,
            static fn ($s) => ExamModel::untukKelas($exam, $s['kelas']) && ! in_array((int) $s['id'], array_map('intval', $ikut), true)
        ));

        return view('admin/hasil', [
            'title' => 'Hasil — ' . $exam['nama'],
            'exam'  => $exam,
            'hasil'        => $hasil,
            'absen'        => $absen,
            'daftarRombel' => ResultExport::classes($hasil),
            'stat'         => [
                'selesai' => count($selesai),
                'proses'  => count($hasil) - count($selesai),
                'rata'    => $skor === [] ? 0 : round(array_sum($skor) / count($skor), 2),
                'max'     => $skor === [] ? 0 : max($skor),
                'min'     => $skor === [] ? 0 : min($skor),
            ],
        ]);
    }

    public function export(int $id)
    {
        $exam = model(ExamModel::class)->withBank($id);
        if (! $exam) {
            return redirect()->to(site_url('admin/ujian'))->with('error', 'Ujian tidak ditemukan.');
        }

        $semua = model(AttemptModel::class)->hasilUjian($id);
        $kelas = trim((string) $this->request->getGet('kelas'));
        if ($kelas !== '' && ! in_array($kelas, ResultExport::classes($semua), true)) {
            return redirect()->to(site_url('admin/ujian/hasil/' . $id))
                ->with('error', 'Rombel tidak ditemukan pada hasil ujian ini.');
        }

        $hasil = ResultExport::filter($semua, $kelas);
        if ($hasil === []) {
            return redirect()->to(site_url('admin/ujian/hasil/' . $id))
                ->with('error', 'Belum ada hasil yang dapat diekspor untuk rombel tersebut.');
        }

        $baris = [];
        foreach ($hasil as $i => $h) {
            $baris[] = [
                $i + 1,
                $h['nis'],
                $h['nama'],
                $h['kelas'],
                $h['status'] === 'selesai' ? 'Selesai' : 'Berlangsung',
                (int) $h['benar'],
                (int) $h['salah'],
                (int) $h['kosong'],
                (float) $h['skor'],
                $h['submitted_at'] ?? '',
            ];
        }

        $nama = ResultExport::slug($exam['nama']);
        $suffix = $kelas === '' ? '' : '-' . ResultExport::slug($kelas);
        Sheet::unduhData(
            'nilai-' . $nama . $suffix . '.xlsx',
            ['No', 'NIS', 'Nama', 'Kelas', 'Status', 'Benar', 'Salah', 'Kosong', 'Nilai', 'Dikumpulkan'],
            $baris
        );
    }

    /**
     * Analisis butir soal: per soal, berapa peserta benar/salah/kosong, tingkat
     * kesukaran, dan sebaran pilihan A-E (untuk melihat pengecoh yang bekerja).
     */
    public function analisis(int $examId)
    {
        $exam = model(ExamModel::class)->withBank($examId);
        if (! $exam) {
            return redirect()->to(site_url('admin/ujian'))->with('error', 'Ujian tidak ditemukan.');
        }

        $attemptModel = model(AttemptModel::class);
        $selesai      = $attemptModel->where('exam_id', $examId)->where('status', 'selesai')->findAll();

        // Soal diacak per peserta, jadi "berapa peserta menerima soal ini"
        // harus dihitung dari urutan masing-masing attempt, bukan dari total peserta.
        $diterima = [];
        foreach ($selesai as $a) {
            foreach ($attemptModel->urutanIds($a) as $qid) {
                $diterima[$qid] = ($diterima[$qid] ?? 0) + 1;
            }
        }

        $rekap   = model(AnswerModel::class)->rekapButir($examId);
        $sebaran = model(AnswerModel::class)->sebaranPilihan($examId);

        $soal = [];
        if ($diterima !== []) {
            $soal = model(QuestionModel::class)->whereIn('id', array_keys($diterima))->orderBy('id')->findAll();
        }

        $butir = [];
        foreach ($soal as $q) {
            $id = (int) $q['id'];
            $n  = $diterima[$id] ?? 0;
            $b  = $rekap[$id]['benar'] ?? 0;
            $s  = $rekap[$id]['salah'] ?? 0;

            $butir[] = $q + [
                'diterima' => $n,
                'benar'    => $b,
                'salah'    => $s,
                'kosong'   => max(0, $n - $b - $s),
                'persen'   => $n > 0 ? round($b / $n * 100, 1) : null,
                'sebaran'  => $sebaran[$id] ?? [],
            ];
        }

        // urutkan dari soal tersulit; jika sama sulitnya, yang lebih banyak
        // dikosongkan didahulukan (indikasi soal membingungkan/kehabisan waktu)
        usort($butir, static function ($x, $y) {
            return [($x['persen'] ?? 101), -$x['kosong']] <=> [($y['persen'] ?? 101), -$y['kosong']];
        });

        return view('admin/analisis', [
            'title'   => 'Analisis Butir — ' . $exam['nama'],
            'exam'    => $exam,
            'butir'   => $butir,
            'peserta' => count($selesai),
        ]);
    }

    /** Detail jawaban satu siswa: benar/salah per nomor + kunci. */
    public function jawaban(int $examId, int $studentId)
    {
        $exam  = model(ExamModel::class)->withBank($examId);
        $siswa = model(StudentModel::class)->find($studentId);
        if (! $exam || ! $siswa) {
            return redirect()->to(site_url('admin/ujian'))->with('error', 'Data tidak ditemukan.');
        }

        $attemptModel = model(AttemptModel::class);
        $attempt      = $attemptModel->findAktif($examId, $studentId);
        if (! $attempt) {
            return redirect()->to(site_url('admin/ujian/hasil/' . $examId))
                ->with('error', 'Siswa ini belum mengerjakan ujian tersebut.');
        }

        $ids   = $attemptModel->urutanIds($attempt);
        $jawab = model(AnswerModel::class)->petaAttempt((int) $attempt['id']);

        $map = [];
        if ($ids !== []) {
            foreach (model(QuestionModel::class)->whereIn('id', $ids)->findAll() as $q) {
                $map[(int) $q['id']] = $q;
            }
        }

        // urutan soal persis seperti yang dilihat siswa
        $daftar = [];
        foreach ($ids as $i => $qid) {
            if (! isset($map[$qid])) {
                continue; // soal dihapus admin setelah ujian
            }
            $isi = $jawab[$qid]['jawaban'] ?? null;
            $daftar[] = $map[$qid] + [
                'no'      => $i + 1,
                'jawaban' => $isi,
                'ragu'    => ! empty($jawab[$qid]['ragu']),
                // ambil dari flag tersimpan; null (belum dinilai) dihitung ulang
                'benar'   => $isi === null || $isi === ''
                    ? null
                    : (isset($jawab[$qid]['benar'])
                        ? (bool) $jawab[$qid]['benar']
                        : strtoupper($isi) === strtoupper((string) $map[$qid]['kunci'])),
            ];
        }

        return view('admin/jawaban', [
            'title'   => 'Jawaban ' . $siswa['nama'],
            'exam'    => $exam,
            'siswa'   => $siswa,
            'attempt' => $attempt,
            'daftar'  => $daftar,
        ]);
    }

    /** Hapus attempt agar siswa bisa mengulang (mis. listrik mati saat ujian). */
    public function resetAttempt(int $examId)
    {
        $studentId = (int) $this->request->getPost('student_id');
        model(AttemptModel::class)->where('exam_id', $examId)->where('student_id', $studentId)->delete();

        return redirect()->back()->with('success', 'Attempt direset, siswa dapat mengerjakan ulang.');
    }
}
