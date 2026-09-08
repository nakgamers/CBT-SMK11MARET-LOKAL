<?php

namespace App\Models;

use CodeIgniter\Model;

class AnswerModel extends Model
{
    protected $table         = 'answers';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['attempt_id', 'question_id', 'jawaban', 'ragu', 'benar'];

    /** @return array<int, array> jawaban di-index question_id */
    public function petaAttempt(int $attemptId): array
    {
        $out = [];
        foreach ($this->where('attempt_id', $attemptId)->findAll() as $r) {
            $out[(int) $r['question_id']] = $r;
        }

        return $out;
    }

    /**
     * Rekap benar/salah per butir soal untuk satu ujian, dari flag `benar` yang
     * ditulis saat penilaian. Hanya attempt 'selesai' dihitung.
     *
     * Catatan: soal yang tidak pernah disentuh siswa TIDAK punya baris di sini,
     * jadi jumlah "kosong" harus dihitung pemanggil dari daftar peserta yang
     * benar-benar menerima soal itu (urutan attempt), bukan dari tabel ini.
     *
     * @return array<int, array{benar:int,salah:int}>
     */
    public function rekapButir(int $examId): array
    {
        $rows = $this->select('answers.question_id,
                SUM(answers.benar = 1) AS benar,
                SUM(answers.benar = 0) AS salah')
            ->join('attempts', 'attempts.id = answers.attempt_id')
            ->where('attempts.exam_id', $examId)
            ->where('attempts.status', 'selesai')
            ->groupBy('answers.question_id')
            ->findAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['question_id']] = [
                'benar' => (int) $r['benar'],
                'salah' => (int) $r['salah'],
            ];
        }

        return $out;
    }

    /**
     * Sebaran pilihan per soal (A-E), untuk melihat pengecoh mana yang menarik
     * jawaban. Hanya attempt selesai.
     *
     * @return array<int, array<string,int>> [question_id][A..E] => jumlah
     */
    public function sebaranPilihan(int $examId): array
    {
        $rows = $this->select('answers.question_id, answers.jawaban, COUNT(*) AS n')
            ->join('attempts', 'attempts.id = answers.attempt_id')
            ->where('attempts.exam_id', $examId)
            ->where('attempts.status', 'selesai')
            ->where('answers.jawaban IS NOT NULL')
            ->groupBy('answers.question_id, answers.jawaban')
            ->findAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['question_id']][strtoupper((string) $r['jawaban'])] = (int) $r['n'];
        }

        return $out;
    }

    /** Simpan/ubah 1 jawaban (autosave). */
    public function simpan(int $attemptId, int $questionId, ?string $jawaban, ?bool $ragu = null): void
    {
        $row  = $this->where('attempt_id', $attemptId)->where('question_id', $questionId)->first();
        $data = ['jawaban' => $jawaban];
        if ($ragu !== null) {
            $data['ragu'] = $ragu ? 1 : 0;
        }

        if ($row) {
            $this->update($row['id'], $data);

            return;
        }

        $this->insert($data + ['attempt_id' => $attemptId, 'question_id' => $questionId]);
    }
}
