<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Sheet;
use App\Models\BankModel;
use App\Models\QuestionModel;

class Questions extends BaseController
{
    public function index(int $bankId)
    {
        $bank = model(BankModel::class)->find($bankId);
        if (! $bank) {
            return redirect()->to(site_url('admin/bank'))->with('error', 'Bank soal tidak ditemukan.');
        }

        return view('admin/soal', [
            'title' => 'Soal — ' . $bank['nama'],
            'bank'  => $bank,
            'soal'  => model(QuestionModel::class)->byBank($bankId),
        ]);
    }

    public function simpan(int $bankId)
    {
        $model = model(QuestionModel::class);
        $id    = (int) $this->request->getPost('id');

        $data = [
            'bank_id' => $bankId,
            'teks'    => trim((string) $this->request->getPost('teks')),
            'opsi_a'  => trim((string) $this->request->getPost('opsi_a')),
            'opsi_b'  => trim((string) $this->request->getPost('opsi_b')),
            'opsi_c'  => trim((string) $this->request->getPost('opsi_c')),
            'opsi_d'  => trim((string) $this->request->getPost('opsi_d')),
            'opsi_e'  => trim((string) $this->request->getPost('opsi_e')),
            'kunci'   => strtoupper(trim((string) $this->request->getPost('kunci'))),
            'bobot'   => max(1, (int) $this->request->getPost('bobot')),
        ];

        // kunci tidak boleh menunjuk opsi kosong (opsi A-E kini wajib semua,
        // cek ini tetap ada sebagai jaring bila validasi model berubah)
        if ($data['kunci'] !== '' && trim((string) ($data['opsi_' . strtolower($data['kunci'])] ?? '')) === '') {
            return redirect()->back()->with('error', 'Kunci ' . $data['kunci'] . ' menunjuk opsi yang kosong.')->withInput();
        }

        $gambar = $this->request->getFile('gambar');
        $gambarBaru = null;
        $dir = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'soal';

        if ($gambar && $gambar->getError() !== UPLOAD_ERR_NO_FILE) {
            if (! $gambar->isValid()) {
                $pesan = match ($gambar->getError()) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran gambar melebihi batas upload server.',
                    UPLOAD_ERR_PARTIAL => 'Upload gambar tidak selesai. Coba pilih gambar lagi.',
                    default => 'Gambar tidak dapat diupload (' . $gambar->getErrorString() . ').',
                };
                log_message('error', 'Upload gambar soal gagal: {m}', ['m' => $pesan]);
                return redirect()->back()->with('error', $pesan)->withInput();
            }

            if ($gambar->getSize() > 8 * 1024 * 1024) {
                return redirect()->back()->with('error', 'Ukuran gambar maksimal 8 MB.')->withInput();
            }

            if (! in_array(strtolower((string) $gambar->getMimeType()), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                return redirect()->back()->with('error', 'Format gambar harus JPG, PNG, GIF, atau WebP.')->withInput();
            }

            if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                log_message('error', 'Direktori gambar soal tidak dapat dibuat: {dir}', ['dir' => $dir]);
                return redirect()->back()->with('error', 'Folder penyimpanan gambar belum siap.')->withInput();
            }

            if (! is_writable($dir)) {
                log_message('error', 'Direktori gambar soal tidak writable: {dir}', ['dir' => $dir]);
                return redirect()->back()->with('error', 'Penyimpanan gambar tidak memiliki izin tulis di server.')->withInput();
            }

            $gambarBaru = $gambar->getRandomName();
            try {
                $gambar->move($dir, $gambarBaru);
            } catch (\Throwable $e) {
                log_message('error', 'Move gambar soal gagal: {m}', ['m' => $e->getMessage()]);
                return redirect()->back()->with('error', 'Gambar gagal disimpan ke server.')->withInput();
            }
            $data['gambar'] = $gambarBaru;
        }

        $lama = $id ? $model->find($id) : null;
        $ok = $id ? $model->update($id, $data) : $model->insert($data);
        if ($ok === false) {
            if ($gambarBaru !== null) {
                @unlink($dir . DIRECTORY_SEPARATOR . $gambarBaru);
            }
            return redirect()->back()->with('error', $model->errors())->withInput();
        }

        if ($gambarBaru !== null && ! empty($lama['gambar']) && $lama['gambar'] !== $gambarBaru) {
            @unlink($dir . DIRECTORY_SEPARATOR . basename((string) $lama['gambar']));
        }

        return redirect()->to(site_url('admin/soal/' . $bankId))
            ->with('success', $id ? 'Soal diperbarui.' : 'Soal ditambahkan.');
    }

    public function hapus(int $bankId, int $id)
    {
        model(QuestionModel::class)->where('bank_id', $bankId)->delete($id);

        return redirect()->back()->with('success', 'Soal dihapus.');
    }

    public function template()
    {
        Sheet::unduhTemplate(
            'template-soal.xlsx',
            ['pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci', 'bobot'],
            [
                ['Ibu kota Indonesia adalah...', 'Bandung', 'Jakarta', 'Surabaya', 'Medan', 'Semarang', 'B', 1],
                ['2 + 3 x 4 = ...', '20', '14', '24', '11', '9', 'B', 2],
            ]
        );
    }

    public function import(int $bankId)
    {
        if (! model(BankModel::class)->find($bankId)) {
            return redirect()->to(site_url('admin/bank'))->with('error', 'Bank soal tidak ditemukan.');
        }

        $file = $this->request->getFile('berkas');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Berkas tidak valid.');
        }
        if (! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'csv'], true)) {
            return redirect()->back()->with('error', 'Format harus .xlsx, .xls, atau .csv.');
        }

        try {
            $rows = Sheet::rows($file->getTempName(), $file->getClientName());
        } catch (\Throwable $e) {
            log_message('error', 'Import soal gagal: {m}', ['m' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Berkas tidak bisa dibaca: ' . $e->getMessage());
        }

        $model = model(QuestionModel::class);
        $masuk = 0;
        $tolak = [];

        foreach ($rows as $n => $r) {
            $baris = $n + 2;
            $kunci = strtoupper(trim($r[6] ?? ''));

            $data = [
                'bank_id' => $bankId,
                'teks'    => $r[0] ?? '',
                'opsi_a'  => $r[1] ?? '',
                'opsi_b'  => $r[2] ?? '',
                'opsi_c'  => $r[3] ?? '',
                'opsi_d'  => $r[4] ?? '',
                'opsi_e'  => $r[5] ?? '',
                'kunci'   => $kunci,
                'bobot'   => max(1, (int) ($r[7] ?? 1)),
            ];

            if (! in_array($kunci, ['A', 'B', 'C', 'D', 'E'], true)) {
                $tolak[] = "Baris {$baris}: kunci '{$kunci}' tidak valid (harus A-E).";

                continue;
            }

            $kosong = [];
            foreach (QuestionModel::OPSI as $k) {
                if (trim((string) $data['opsi_' . strtolower($k)]) === '') {
                    $kosong[] = $k;
                }
            }
            if ($kosong !== []) {
                $tolak[] = "Baris {$baris}: opsi " . implode('/', $kosong) . ' kosong (A-E wajib diisi semua).';

                continue;
            }

            if ($model->insert($data) === false) {
                $tolak[] = "Baris {$baris}: " . implode(' ', $model->errors());

                continue;
            }
            $masuk++;
        }

        $r = redirect()->to(site_url('admin/soal/' . $bankId))->with('success', "{$masuk} soal berhasil diimport.");

        return $tolak === [] ? $r : $r->with('error', array_slice($tolak, 0, 15));
    }
}
