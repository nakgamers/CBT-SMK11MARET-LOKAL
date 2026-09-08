<?php

namespace App\Models;

use CodeIgniter\Model;

class QuestionModel extends Model
{
    protected $table         = 'questions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'bank_id', 'teks', 'gambar',
        'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e',
        'kunci', 'bobot',
    ];

    protected $validationRules = [
        'bank_id' => 'required|is_natural_no_zero|is_not_unique[banks.id]',
        'teks'    => 'required',
        'opsi_a'  => 'required',
        'opsi_b'  => 'required',
        'opsi_c'  => 'required',
        'opsi_d'  => 'required',
        'opsi_e'  => 'required',
        'kunci'   => 'required|in_list[A,B,C,D,E]',
        'bobot'   => 'permit_empty|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'bank_id' => ['is_not_unique' => 'Bank soal tidak ditemukan.'],
        'teks'    => ['required' => 'Pertanyaan wajib diisi.'],
        'opsi_a'  => ['required' => 'Opsi A wajib diisi.'],
        'opsi_b'  => ['required' => 'Opsi B wajib diisi.'],
        'opsi_c'  => ['required' => 'Opsi C wajib diisi.'],
        'opsi_d'  => ['required' => 'Opsi D wajib diisi.'],
        'opsi_e'  => ['required' => 'Opsi E wajib diisi.'],
        'kunci'   => ['in_list' => 'Kunci jawaban harus A-E.'],
    ];

    /** Opsi pilihan ganda yang dipakai aplikasi ini. */
    public const OPSI = ['A', 'B', 'C', 'D', 'E'];

    public function byBank(int $bankId): array
    {
        return $this->where('bank_id', $bankId)->orderBy('id')->findAll();
    }

    /**
     * Ambil id soal untuk sebuah ujian, diacak/dipotong sesuai konfigurasi.
     *
     * @return list<int>
     */
    public function idsUntukUjian(int $bankId, int $jumlah, bool $acak): array
    {
        $b = $this->select('id')->where('bank_id', $bankId);
        $b = $acak ? $b->orderBy('id', 'RANDOM') : $b->orderBy('id', 'ASC');

        $ids = array_map('intval', array_column($b->findAll(), 'id'));

        return $jumlah > 0 ? array_slice($ids, 0, $jumlah) : $ids;
    }
}
