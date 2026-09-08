<?php

namespace App\Models;

use CodeIgniter\Model;

class ExamModel extends Model
{
    protected $table         = 'exams';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'nama', 'bank_id', 'kelas', 'jumlah_soal', 'durasi_menit',
        'mulai_at', 'selesai_at', 'acak_soal', 'acak_opsi',
        'tampilkan_hasil', 'token', 'aktif',
    ];

    protected $validationRules = [
        'nama'         => 'required|max_length[150]',
        'bank_id'      => 'required|is_natural_no_zero|is_not_unique[banks.id]',
        'durasi_menit' => 'required|is_natural_no_zero',
        'jumlah_soal'  => 'permit_empty|is_natural',
        'mulai_at'     => 'required|valid_date[Y-m-d H:i:s]',
        'selesai_at'   => 'required|valid_date[Y-m-d H:i:s]',
    ];

    protected $validationMessages = [
        'bank_id'      => ['is_not_unique' => 'Bank soal tidak ditemukan.'],
        'nama'         => ['required' => 'Nama ujian wajib diisi.'],
        'durasi_menit' => ['required' => 'Durasi wajib diisi.'],
        'mulai_at'     => ['required' => 'Waktu mulai wajib diisi.'],
        'selesai_at'   => ['required' => 'Waktu selesai wajib diisi.'],
    ];

    /**
     * Status rentang waktu ujian: belum | berlangsung | lewat | nonaktif.
     * Satu-satunya sumber kebenaran soal waktu — controller & view memanggil ini.
     */
    public static function statusWaktu(array $exam, ?int $now = null): string
    {
        $now ??= time();

        if ((int) ($exam['aktif'] ?? 1) !== 1) {
            return 'nonaktif';
        }
        if ($now < strtotime((string) $exam['mulai_at'])) {
            return 'belum';
        }
        if ($now > strtotime((string) $exam['selesai_at'])) {
            return 'lewat';
        }

        return 'berlangsung';
    }

    public static function labelStatus(string $status): string
    {
        return [
            'belum'       => 'Belum dimulai',
            'berlangsung' => 'Sedang berlangsung',
            'lewat'       => 'Sudah berakhir',
            'nonaktif'    => 'Nonaktif',
        ][$status] ?? $status;
    }

    /** Ujian boleh diikuti kelas ini? '*' = semua kelas. */
    public static function untukKelas(array $exam, string $kelas): bool
    {
        $target = trim((string) ($exam['kelas'] ?? '*'));
        if ($target === '' || $target === '*') {
            return true;
        }

        $list = array_map('trim', explode(',', $target));

        return in_array($kelas, $list, true);
    }

    /** Daftar ujian + nama bank/mapel + jumlah peserta, untuk dashboard admin. */
    public function withRingkasan(): array
    {
        return $this->select('exams.*, banks.nama AS bank_nama, banks.mapel AS bank_mapel,
                (SELECT COUNT(*) FROM attempts a WHERE a.exam_id = exams.id) AS jumlah_peserta,
                (SELECT COUNT(*) FROM attempts a WHERE a.exam_id = exams.id AND a.status = "selesai") AS jumlah_selesai')
            ->join('banks', 'banks.id = exams.bank_id', 'left')
            ->orderBy('exams.mulai_at', 'DESC')
            ->findAll();
    }

    public function withBank(int $id): ?array
    {
        return $this->select('exams.*, banks.nama AS bank_nama, banks.mapel AS bank_mapel')
            ->join('banks', 'banks.id = exams.bank_id', 'left')
            ->where('exams.id', $id)   // wajib dikualifikasi: query ini pakai JOIN
            ->first();
    }

    /** Ujian yang relevan untuk seorang siswa (kelasnya cocok & aktif). */
    public function untukSiswa(string $kelas): array
    {
        $rows = $this->select('exams.*, banks.nama AS bank_nama, banks.mapel AS bank_mapel')
            ->join('banks', 'banks.id = exams.bank_id', 'left')
            ->where('exams.aktif', 1)
            ->orderBy('exams.mulai_at', 'ASC')
            ->findAll();

        return array_values(array_filter($rows, static fn ($e) => self::untukKelas($e, $kelas)));
    }
}
