<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table         = 'students';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['nis', 'nama', 'kelas', 'jk', 'token', 'aktif'];

    protected $validationRules = [
        'nis'   => 'required|max_length[30]|is_unique[students.nis,id,{id}]',
        'nama'  => 'required|max_length[100]',
        'kelas' => 'required|max_length[30]',
        'jk'    => 'required|in_list[L,P]',
        'token' => 'required|min_length[4]|max_length[12]',
    ];

    protected $validationMessages = [
        'nis' => [
            'required'  => 'NIS wajib diisi.',
            'is_unique' => 'NIS sudah dipakai siswa lain.',
        ],
        'nama'  => ['required' => 'Nama wajib diisi.'],
        'kelas' => ['required' => 'Kelas wajib diisi.'],
    ];

    /** Login kartu: NIS + token. */
    public function findByCard(string $nis, string $token): ?array
    {
        return $this->where('nis', $nis)
            ->where('token', $token)
            ->where('aktif', 1)
            ->first();
    }

    /** Daftar kelas unik untuk dropdown filter. */
    public function daftarKelas(): array
    {
        return array_column(
            $this->select('kelas')->distinct()->orderBy('kelas')->findAll(),
            'kelas'
        );
    }

    /**
     * Kelas + jumlah siswa aktif, untuk daftar centang peserta ujian.
     *
     * @return list<array{kelas: string, jumlah: int}>
     */
    public function kelasDenganJumlah(): array
    {
        $rows = $this->select('kelas, COUNT(*) AS jumlah')
            ->where('aktif', 1)
            ->groupBy('kelas')
            ->orderBy('kelas')
            ->findAll();

        return array_map(
            static fn ($r) => ['kelas' => (string) $r['kelas'], 'jumlah' => (int) $r['jumlah']],
            $rows
        );
    }

    /** Token 6 karakter tanpa karakter kembar (0/O 1/I/L 2/Z 5/S 8/B) — dicetak di kartu. */
    public static function generateToken(int $len = 6): string
    {
        $pool = 'ACDEFGHJKMNPQRTUVWXY34679';
        $out  = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $pool[random_int(0, strlen($pool) - 1)];
        }

        return $out;
    }
}
