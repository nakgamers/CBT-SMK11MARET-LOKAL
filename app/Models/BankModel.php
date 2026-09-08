<?php

namespace App\Models;

use CodeIgniter\Model;

class BankModel extends Model
{
    protected $table         = 'banks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['nama', 'mapel', 'keterangan'];

    protected $validationRules = [
        'nama'  => 'required|max_length[120]',
        'mapel' => 'required|max_length[80]',
    ];

    protected $validationMessages = [
        'nama'  => ['required' => 'Nama bank soal wajib diisi.'],
        'mapel' => ['required' => 'Mata pelajaran wajib diisi.'],
    ];

    /** Bank + jumlah soal, untuk tabel daftar bank. */
    public function withJumlahSoal(): array
    {
        return $this->select('banks.*, (SELECT COUNT(*) FROM questions q WHERE q.bank_id = banks.id) AS jumlah_soal')
            ->orderBy('banks.mapel')
            ->orderBy('banks.nama')
            ->findAll();
    }
}
