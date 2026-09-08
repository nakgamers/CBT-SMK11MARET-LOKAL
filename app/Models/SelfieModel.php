<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Metadata absen selfie. Foto ada di disk (SelfieStore), DB hanya path.
 */
class SelfieModel extends Model
{
    protected $table         = 'selfies';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'exam_id', 'student_id', 'file_path', 'thumb_path',
        'file_size', 'thumb_size', 'width', 'height', 'hash', 'ip',
    ];

    /** Selfie milik siswa pada satu ujian (null bila belum absen). */
    public function untukSiswa(int $examId, int $studentId): ?array
    {
        return $this->where('exam_id', $examId)
            ->where('student_id', $studentId)
            ->first();
    }

    /**
     * Galeri admin dengan filter + pagination ringan.
     * Hanya kolom yang dibutuhkan (tanpa SELECT *), join nama siswa/ujian.
     */
    public function galeri(array $f = [], int $limit = 48, int $offset = 0): array
    {
        $q = $this->db->table('selfies s')
            ->select('s.id, s.file_path, s.thumb_path, s.file_size, s.created_at,
                      s.width, s.height, stu.nis, stu.nama, stu.kelas, e.nama AS ujian')
            ->join('students stu', 'stu.id = s.student_id')
            ->join('exams e', 'e.id = s.exam_id');

        if (! empty($f['exam_id'])) {
            $q->where('s.exam_id', (int) $f['exam_id']);
        }
        if (! empty($f['kelas'])) {
            $q->where('stu.kelas', $f['kelas']);
        }
        if (! empty($f['tanggal'])) {
            $q->where('DATE(s.created_at)', $f['tanggal']);
        }
        if (! empty($f['q'])) {
            $like = '%' . $f['q'] . '%';
            $q->groupStart()->like('stu.nama', $like, 'after')->orLike('stu.nis', $like, 'after')->groupEnd();
        }

        $total = (clone $q)->countAllResults(false);
        $rows  = $q->orderBy('s.created_at', 'DESC')->limit($limit, $offset)->get()->getResultArray();

        return [$rows, $total];
    }

    /** Rekap jumlah selfie per ujian (untuk daftar ujian admin). */
    public function hitungPerUjian(): array
    {
        $rows = $this->db->table('selfies')
            ->select('exam_id, COUNT(*) n')
            ->groupBy('exam_id')
            ->get()->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['exam_id']] = (int) $r['n'];
        }
        return $out;
    }

    /** Total ukuran file di disk (byte) untuk info penyimpanan admin. */
    public function totalByte(): int
    {
        $row = $this->db->table('selfies')->selectSum('file_size', 'b')->get()->getRow();
        return (int) ($row->b ?? 0);
    }
}
