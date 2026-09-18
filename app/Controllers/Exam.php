<?php

namespace App\Controllers;

use App\Models\AnswerModel;
use App\Models\AttemptModel;
use App\Models\ExamModel;
use App\Models\QuestionModel;

class Exam extends BaseController
{
    /** Halaman konfirmasi + tombol mulai (juga pintu masuk token ujian). */
    public function mulai(int $examId)
    {
        $siswa = $this->siswa();
        $exam  = model(ExamModel::class)->withBank($examId);

        $tolak = $this->tolak($exam, $siswa);
        if ($tolak !== null) {
            return $tolak;
        }

        $attempt = model(AttemptModel::class)->findAktif($examId, (int) $siswa['id']);
        if ($attempt) {
            return redirect()->to(site_url($attempt['status'] === 'selesai'
                ? 'siswa/hasil/' . $examId
                : 'siswa/kerjakan/' . $examId));
        }

        // Absen selfie: wajib sebelum attempt dibuat (anti-joki).
        if ((int) ($exam['absen_selfie'] ?? 0) === 1
            && ! model(\App\Models\SelfieModel::class)->untukSiswa($examId, (int) $siswa['id'])) {
            $token = trim((string) $this->request->getGet('token'));
            $url = site_url('siswa/absen/' . $examId);
            if ($token !== '') {
                $url .= '?token=' . rawurlencode($token);
            }
            return redirect()->to($url);
        }

        $jumlah = count(model(QuestionModel::class)->idsUntukUjian(
            (int) $exam['bank_id'],
            (int) $exam['jumlah_soal'],
            false
        ));

        return view('siswa/mulai', [
            'title'  => $exam['nama'],
            'siswa'  => $siswa,
            'exam'   => $exam,
            'jumlah' => $jumlah,
        ]);
    }

    public function kerjakan(int $examId)
    {
        $siswa = $this->siswa();
        $exam  = model(ExamModel::class)->withBank($examId);

        $tolak = $this->tolak($exam, $siswa);
        if ($tolak !== null) {
            return $tolak;
        }

        $attemptModel = model(AttemptModel::class);
        $attempt      = $attemptModel->findAktif($examId, (int) $siswa['id']);

        // Belum ada attempt: ini POST/GET pertama -> validasi token lalu buat.
        if (! $attempt) {
            // gerbang kedua: akses langsung /kerjakan tanpa selfie ditolak
            if ((int) ($exam['absen_selfie'] ?? 0) === 1
                && ! model(\App\Models\SelfieModel::class)->untukSiswa($examId, (int) $siswa['id'])) {
                return redirect()->to(site_url('siswa/absen/' . $examId));
            }
            if (! empty($exam['token'])) {
                $kirim = strtoupper(trim((string) $this->request->getGet('token')));
                if ($kirim !== strtoupper((string) $exam['token'])) {
                    return redirect()->to(site_url('siswa/ujian/' . $examId))
                        ->with('error', 'Token ujian salah.');
                }
            }
            $attempt = $this->buatAttempt($exam, (int) $siswa['id']);
        }

        if ($attempt['status'] === 'selesai') {
            return redirect()->to(site_url('siswa/hasil/' . $examId));
        }

        // Waktu habis (deadline attempt atau jadwal ujian) -> nilai sekarang.
        if (time() >= strtotime((string) $attempt['deadline_at'])) {
            $attemptModel->finalisasi((int) $attempt['id']);

            return redirect()->to(site_url('siswa/hasil/' . $examId))
                ->with('info', 'Waktu ujian sudah berakhir, jawaban terakhir otomatis dikumpulkan.');
        }

        $ids   = $attemptModel->urutanIds($attempt);
        $soal  = $this->soalTerurut($ids, (bool) $exam['acak_opsi'], (int) $attempt['id']);
        $jawab = model(AnswerModel::class)->petaAttempt((int) $attempt['id']);

        return view('siswa/kerjakan', [
            'title'   => $exam['nama'],
            'siswa'   => $siswa,
            'exam'    => $exam,
            'attempt' => $attempt,
            'soal'    => $soal,
            'jawab'   => $jawab,
            'sisa'    => strtotime((string) $attempt['deadline_at']) - time(),
        ]);
    }

    /** Autosave 1 jawaban (AJAX). */
    public function jawab(int $examId)
    {
        $siswa = $this->siswa();
        if (! $siswa) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'Sesi tidak valid.']);
        }

        $attempt = model(AttemptModel::class)->findAktif($examId, (int) $siswa['id']);

        if (! $attempt || $attempt['status'] === 'selesai') {
            return $this->response->setStatusCode(409)->setJSON(['ok' => false, 'error' => 'Ujian sudah ditutup.']);
        }

        $habis = time() >= strtotime((string) $attempt['deadline_at']);
        if ($habis) {
            model(AttemptModel::class)->finalisasi((int) $attempt['id']);

            return $this->response->setStatusCode(409)->setJSON([
                'ok'    => false,
                'habis' => true,
                'error' => 'Waktu habis.',
            ]);
        }

        $qid     = (int) $this->request->getPost('question_id');
        $jawaban = strtoupper(trim((string) $this->request->getPost('jawaban')));
        $ragu    = $this->request->getPost('ragu');

        // soal harus benar-benar milik attempt ini
        if (! in_array($qid, model(AttemptModel::class)->urutanIds($attempt), true)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Soal tidak valid.']);
        }
        if ($jawaban !== '' && ! in_array($jawaban, ['A', 'B', 'C', 'D', 'E'], true)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Pilihan tidak valid.']);
        }

        model(AnswerModel::class)->simpan(
            (int) $attempt['id'],
            $qid,
            $jawaban === '' ? null : $jawaban,
            $ragu === null ? null : (bool) (int) $ragu
        );

        return $this->response->setJSON([
            'ok'   => true,
            'sisa' => max(0, strtotime((string) $attempt['deadline_at']) - time()),
        ]);
    }

    /** Catat perpindahan tab/jendela dan reset jawaban sesuai kebijakan anti-cheat. */
    public function pelanggaran(int $examId)
    {
        $siswa = $this->siswa();
        if (! $siswa) {
            return $this->response->setStatusCode(401)->setJSON([
                'ok'    => false,
                'login' => false,
                'error' => 'Sesi tidak valid.',
            ]);
        }

        $attempt = model(AttemptModel::class)->findAktif($examId, (int) $siswa['id']);
        if (! $attempt || $attempt['status'] === 'selesai') {
            return $this->response->setStatusCode(409)->setJSON([
                'ok'    => false,
                'selesai' => true,
                'error' => 'Ujian sudah ditutup.',
            ]);
        }

        $hasil = model(AttemptModel::class)->catatPelanggaran((int) $attempt['id']);
        if ($hasil === null) {
            return $this->response->setStatusCode(409)->setJSON([
                'ok'      => false,
                'selesai' => true,
                'error'   => 'Ujian sudah ditutup.',
            ]);
        }

        $pelanggaran = (int) $hasil['pelanggaran'];
        $dihentikan  = (bool) $hasil['dihentikan'];
        if ($dihentikan) {
            session()->destroy();
        }

        return $this->response->setJSON([
            'ok'          => true,
            'pelanggaran' => $pelanggaran,
            'maksimal'    => 3,
            'dihentikan'  => $dihentikan,
            'redirect'    => $dihentikan ? site_url('login') : null,
        ]);
    }

    /** Catat gangguan koneksi; jawaban tersimpan tidak dihapus. */
    public function gangguanKoneksi(int $examId)
    {
        $siswa = $this->siswa();
        if (! $siswa) {
            return $this->response->setStatusCode(401)->setJSON([
                'ok' => false, 'login' => false, 'error' => 'Sesi tidak valid.',
            ]);
        }

        $attempt = model(AttemptModel::class)->findAktif($examId, (int) $siswa['id']);
        if (! $attempt || $attempt['status'] === 'selesai') {
            return $this->response->setStatusCode(409)->setJSON([
                'ok' => false, 'selesai' => true, 'error' => 'Ujian sudah ditutup.',
            ]);
        }

        $hasil = model(AttemptModel::class)->catatGangguanKoneksi((int) $attempt['id']);
        if ($hasil === null) {
            return $this->response->setStatusCode(409)->setJSON([
                'ok' => false, 'selesai' => true, 'error' => 'Ujian sudah ditutup.',
            ]);
        }

        $gangguan = (int) $hasil['gangguan'];
        $dihentikan = (bool) $hasil['dihentikan'];
        if ($dihentikan) {
            session()->destroy();
        }

        return $this->response->setJSON([
            'ok' => true,
            'gangguan' => $gangguan,
            'maksimal' => 5,
            'dihentikan' => $dihentikan,
            'redirect' => $dihentikan ? site_url('login') : null,
        ]);
    }

    public function selesai(int $examId)
    {
        $siswa = $this->siswa();
        if (! $siswa) {
            return redirect()->to(site_url('logout'));
        }

        $attempt = model(AttemptModel::class)->findAktif($examId, (int) $siswa['id']);

        if (! $attempt) {
            return redirect()->to(site_url('siswa'))->with('error', 'Ujian belum dikerjakan.');
        }

        model(AttemptModel::class)->finalisasi((int) $attempt['id']);

        return redirect()->to(site_url('siswa/hasil/' . $examId))->with('success', 'Ujian selesai, jawaban tersimpan.');
    }

    public function hasil(int $examId)
    {
        $siswa = $this->siswa();
        if (! $siswa) {
            return redirect()->to(site_url('logout'));
        }

        $exam    = model(ExamModel::class)->withBank($examId);
        $attempt = $exam ? model(AttemptModel::class)->findAktif($examId, (int) $siswa['id']) : null;

        if (! $exam || ! $attempt) {
            return redirect()->to(site_url('siswa'))->with('error', 'Data hasil tidak ditemukan.');
        }

        if ($attempt['status'] !== 'selesai') {
            // masih berlangsung & waktu masih ada -> kembalikan ke lembar kerja
            if (time() < strtotime((string) $attempt['deadline_at'])) {
                return redirect()->to(site_url('siswa/kerjakan/' . $examId));
            }
            $attempt = model(AttemptModel::class)->finalisasi((int) $attempt['id']);
        }

        /*
         * Dulu halaman ini tetap menyajikan nilai walau "tampilkan_hasil" tidak
         * dicentang — tombolnya saja yang disembunyikan di dashboard, sedangkan
         * URL-nya masih bisa dibuka langsung/dari bookmark. Sekarang aturannya
         * ditegakkan di sisi server.
         */
        if ((int) $exam['tampilkan_hasil'] !== 1) {
            return redirect()->to(site_url('siswa'))
                ->with('info', 'Jawaban Anda sudah dikumpulkan dan disimpan.');
        }

        return view('siswa/hasil', [
            'title'   => 'Hasil — ' . $exam['nama'],
            'siswa'   => $siswa,
            'exam'    => $exam,
            'attempt' => $attempt,
        ]);
    }

    // ------------------------------------------------------------------ util

    /** Semua penolakan akses ujian dikumpulkan di sini (dipakai 3 aksi). */
    private function tolak(?array $exam, ?array $siswa)
    {
        if (! $siswa) {
            return redirect()->to(site_url('logout'));
        }
        if (! $exam) {
            return redirect()->to(site_url('siswa'))->with('error', 'Ujian tidak ditemukan.');
        }
        if (! ExamModel::untukKelas($exam, $siswa['kelas'])) {
            return redirect()->to(site_url('siswa'))->with('error', 'Ujian ini bukan untuk kelas Anda.');
        }

        $status = ExamModel::statusWaktu($exam);
        if ($status !== 'berlangsung') {
            return redirect()->to(site_url('siswa'))->with('error', match ($status) {
                'belum'    => 'Ujian belum dimulai. Mulai ' . tgl_id($exam['mulai_at']) . '.',
                'lewat'    => 'Ujian sudah berakhir pada ' . tgl_id($exam['selesai_at']) . '.',
                'nonaktif' => 'Ujian dinonaktifkan oleh admin.',
                default    => 'Ujian tidak dapat diakses.',
            });
        }

        return null;
    }

    private function buatAttempt(array $exam, int $studentId): array
    {
        $ids = model(QuestionModel::class)->idsUntukUjian(
            (int) $exam['bank_id'],
            (int) $exam['jumlah_soal'],
            (bool) $exam['acak_soal']
        );

        // deadline = paling cepat antara (mulai kerja + durasi) dan akhir jadwal
        $deadline = min(time() + (int) $exam['durasi_menit'] * 60, strtotime((string) $exam['selesai_at']));

        $model = model(AttemptModel::class);
        $id    = $model->insert([
            'exam_id'     => (int) $exam['id'],
            'student_id'  => $studentId,
            'urutan'      => json_encode(array_values($ids)),
            'started_at'  => date('Y-m-d H:i:s'),
            'deadline_at' => date('Y-m-d H:i:s', $deadline),
            'status'      => 'berlangsung',
            'ip'          => $this->request->getIPAddress(),
        ], true);

        return $model->find($id);
    }

    /**
     * Ambil soal sesuai urutan $ids. Bila $acakOpsi, urutan opsi diacak
     * deterministik per (attempt, soal) — tidak perlu disimpan, hasilnya
     * sama setiap kali halaman dibuka.
     *
     * @param list<int> $ids
     */
    private function soalTerurut(array $ids, bool $acakOpsi, int $attemptId): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = model(QuestionModel::class)->whereIn('id', $ids)->findAll();
        $map  = [];
        foreach ($rows as $r) {
            $map[(int) $r['id']] = $r;
        }

        $out = [];
        foreach ($ids as $qid) {
            if (! isset($map[$qid])) {
                continue; // soal dihapus admin setelah ujian dimulai
            }
            $q    = $map[$qid];
            $urut = [];
            foreach (['A', 'B', 'C', 'D', 'E'] as $k) {
                if (($q['opsi_' . strtolower($k)] ?? '') !== '' && $q['opsi_' . strtolower($k)] !== null) {
                    $urut[] = $k;
                }
            }

            if ($acakOpsi && count($urut) > 1) {
                mt_srand($attemptId * 100000 + $qid);
                shuffle($urut);
                mt_srand();
            }

            $q['urut_opsi'] = $urut;
            $out[]          = $q;
        }

        return $out;
    }
}
