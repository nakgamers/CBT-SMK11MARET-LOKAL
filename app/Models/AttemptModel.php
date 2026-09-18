<?php

namespace App\Models;

use CodeIgniter\Model;

class AttemptModel extends Model
{
    protected $table         = 'attempts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'exam_id', 'student_id', 'urutan', 'started_at', 'deadline_at',
        'submitted_at', 'status', 'skor', 'benar', 'salah', 'kosong', 'ip',
        'pelanggaran_cheat', 'pelanggaran_terakhir_at',
        'gangguan_koneksi', 'gangguan_koneksi_terakhir_at',
    ];

    public function findAktif(int $examId, int $studentId): ?array
    {
        return $this->where('exam_id', $examId)->where('student_id', $studentId)->first();
    }

    /** Hasil per ujian untuk tabel nilai admin. */
    public function hasilUjian(int $examId): array
    {
        return $this->select('attempts.*, students.nis, students.nama, students.kelas')
            ->join('students', 'students.id = attempts.student_id', 'left')
            ->where('attempts.exam_id', $examId)
            ->orderBy('students.kelas')
            ->orderBy('students.nama')
            ->findAll();
    }

    /** @return array<int, array> attempt milik siswa, di-index exam_id */
    public function petaSiswa(int $studentId): array
    {
        $rows = $this->where('student_id', $studentId)->findAll();
        $out  = [];
        foreach ($rows as $r) {
            $out[(int) $r['exam_id']] = $r;
        }

        return $out;
    }

    /** @return list<int> */
    public function urutanIds(array $attempt): array
    {
        return array_map('intval', json_decode((string) $attempt['urutan'], true) ?: []);
    }

    /**
     * Catat siswa meninggalkan halaman ujian dan kosongkan seluruh jawabannya.
     * Pada pelanggaran ketiga attempt langsung difinalisasi dengan jawaban kosong.
     *
     * @return array{attempt: array, pelanggaran: int, dihentikan: bool}|null
     */
    public function catatPelanggaran(int $attemptId): ?array
    {
        $this->db->transStart();

        // Kunci baris attempt agar dua visibility event bersamaan tidak kehilangan hitungan.
        $attempt = $this->db->query(
            'SELECT * FROM attempts WHERE id = ? FOR UPDATE',
            [$attemptId]
        )->getRowArray();
        if (! $attempt || $attempt['status'] !== 'berlangsung') {
            $this->db->transComplete();

            return null;
        }

        $pelanggaran = min(3, (int) ($attempt['pelanggaran_cheat'] ?? 0) + 1);
        $this->db->table('answers')->where('attempt_id', $attemptId)->delete();
        $this->update($attemptId, [
            'pelanggaran_cheat'       => $pelanggaran,
            'pelanggaran_terakhir_at' => date('Y-m-d H:i:s'),
        ]);

        $dihentikan = $pelanggaran >= 3;
        if ($dihentikan) {
            $this->finalisasi($attemptId);
        }

        $this->db->transComplete();

        return [
            'attempt'     => $this->find($attemptId),
            'pelanggaran' => $pelanggaran,
            'dihentikan'  => $dihentikan,
        ];
    }

    /**
     * Catat gangguan koneksi tanpa menghapus jawaban yang sudah tersimpan.
     * Pada gangguan kelima attempt langsung difinalisasi.
     *
     * @return array{attempt: array, gangguan: int, dihentikan: bool}|null
     */
    public function catatGangguanKoneksi(int $attemptId): ?array
    {
        $this->db->transStart();
        $attempt = $this->db->query(
            'SELECT * FROM attempts WHERE id = ? FOR UPDATE',
            [$attemptId]
        )->getRowArray();
        if (! $attempt || $attempt['status'] !== 'berlangsung') {
            $this->db->transComplete();

            return null;
        }

        $gangguan = min(5, (int) ($attempt['gangguan_koneksi'] ?? 0) + 1);
        $this->update($attemptId, [
            'gangguan_koneksi'            => $gangguan,
            'gangguan_koneksi_terakhir_at' => date('Y-m-d H:i:s'),
        ]);

        $dihentikan = $gangguan >= 5;
        if ($dihentikan) {
            $this->finalisasi($attemptId);
        }

        $this->db->transComplete();

        return [
            'attempt'  => $this->find($attemptId),
            'gangguan' => $gangguan,
            'dihentikan' => $dihentikan,
        ];
    }

    /**
     * Nilai attempt & tandai selesai. Satu-satunya tempat skor dihitung —
     * dipakai submit manual maupun auto-submit saat waktu habis.
     * Idempoten: attempt yang sudah 'selesai' langsung dikembalikan apa adanya.
     */
    public function finalisasi(int $attemptId): array
    {
        $attempt = $this->find($attemptId);
        if (! $attempt) {
            throw new \RuntimeException('Attempt tidak ditemukan: ' . $attemptId);
        }
        if ($attempt['status'] === 'selesai') {
            return $attempt;
        }

        $ids = $this->urutanIds($attempt);
        if ($ids === []) {
            $ids = [0]; // hindari pembagian nol pada ujian tanpa soal
        }

        $kunci = [];
        $bobot = [];
        foreach (model(QuestionModel::class)->whereIn('id', $ids)->findAll() as $q) {
            $kunci[(int) $q['id']] = strtoupper((string) $q['kunci']);
            $bobot[(int) $q['id']] = max(1, (int) $q['bobot']);
        }

        $jawab       = model(AnswerModel::class)->petaAttempt($attemptId);
        $answerModel = model(AnswerModel::class);

        $benar = $salah = $kosong = 0;
        $poin  = 0;
        $total = 0;

        foreach ($ids as $qid) {
            $total += $bobot[$qid] ?? 1;
            $isi = strtoupper((string) ($jawab[$qid]['jawaban'] ?? ''));

            if ($isi === '') {
                $kosong++;

                continue;
            }

            $cocok = isset($kunci[$qid]) && $isi === $kunci[$qid];
            if ($cocok) {
                $benar++;
                $poin += $bobot[$qid] ?? 1;
            } else {
                $salah++;
            }

            if (isset($jawab[$qid]['id'])) {
                $answerModel->update($jawab[$qid]['id'], ['benar' => $cocok ? 1 : 0]);
            }
        }

        $skor = $total > 0 ? round($poin / $total * 100, 2) : 0.0;

        $this->update($attemptId, [
            'status'       => 'selesai',
            'submitted_at' => date('Y-m-d H:i:s'),
            'benar'        => $benar,
            'salah'        => $salah,
            'kosong'       => $kosong,
            'skor'         => $skor,
        ]);

        return $this->find($attemptId);
    }
}
