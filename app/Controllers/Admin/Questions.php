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
        $rich  = $this->request->getPost('_format') === 'rich-v1';
        $isi   = static function ($nilai) use ($rich): string {
            $nilai = trim((string) $nilai);

            return $rich ? cbt_siapkan_html_soal($nilai) : cbt_bersihkan_office($nilai);
        };

        $data = [
            'bank_id' => $bankId,
            'teks'    => $isi($this->request->getPost('teks')),
            'opsi_a'  => $isi($this->request->getPost('opsi_a')),
            'opsi_b'  => $isi($this->request->getPost('opsi_b')),
            'opsi_c'  => $isi($this->request->getPost('opsi_c')),
            'opsi_d'  => $isi($this->request->getPost('opsi_d')),
            'opsi_e'  => $isi($this->request->getPost('opsi_e')),
            'kunci'   => strtoupper(trim((string) $this->request->getPost('kunci'))),
            'bobot'   => max(1, (int) $this->request->getPost('bobot')),
        ];

        if ($rich) {
            foreach (['teks' => 'Pertanyaan', 'opsi_a' => 'Opsi A', 'opsi_b' => 'Opsi B', 'opsi_c' => 'Opsi C', 'opsi_d' => 'Opsi D', 'opsi_e' => 'Opsi E'] as $kolom => $label) {
                if (cbt_html_teks_kosong($data[$kolom])) {
                    return redirect()->back()->with('error', $label . ' wajib diisi.')->withInput();
                }
            }
        }

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

    /** Upload gambar rumus dari clipboard/editor; hanya gambar raster lokal. */
    public function uploadInline(int $bankId)
    {
        if (! model(BankModel::class)->find($bankId)) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Bank soal tidak ditemukan.']);
        }

        $file = $this->request->getFile('gambar');
        if ($file === null || ! $file->isValid()) {
            $pesan = $file?->getError() === UPLOAD_ERR_INI_SIZE
                ? 'Gambar terlalu besar. Maksimal 2 MB.'
                : 'Gambar dari clipboard tidak dapat dibaca.';

            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $pesan]);
        }
        if ($file->getSize() < 1 || $file->getSize() > 2 * 1024 * 1024) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Ukuran gambar maksimal 2 MB.']);
        }

        $mime = strtolower((string) $file->getMimeType());
        $ekstensi = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ][$mime] ?? null;
        if ($ekstensi === null) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Gambar harus PNG, JPG, GIF, atau WebP.']);
        }

        $ukuran = @getimagesize($file->getTempName());
        $lebar  = (int) ($ukuran[0] ?? 0);
        $tinggi = (int) ($ukuran[1] ?? 0);
        if ($lebar < 1 || $tinggi < 1 || $lebar > 6000 || $tinggi > 6000 || $lebar * $tinggi > 16000000) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Dimensi gambar tidak valid atau terlalu besar.']);
        }

        $dir = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'soal-inline';
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'error' => 'Folder gambar belum siap.']);
        }

        $nama = bin2hex(random_bytes(16)) . '.' . $ekstensi;
        try {
            $file->move($dir, $nama);
        } catch (\Throwable $e) {
            log_message('error', 'Upload gambar inline soal gagal: {m}', ['m' => $e->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'error' => 'Gambar gagal disimpan.']);
        }

        return $this->response->setJSON([
            'ok'     => true,
            'path'   => '/uploads/soal-inline/' . $nama,
            'width'  => $lebar,
            'height' => $tinggi,
        ]);
    }

    public function hapus(int $bankId, int $id)
    {
        model(QuestionModel::class)->where('bank_id', $bankId)->delete($id);

        return redirect()->back()->with('success', 'Soal dihapus.');
    }

    public function template()
    {
        Sheet::unduhTemplate(
            'template-soal-topik.xlsx',
            ['No', 'Jenis', 'Kode', 'Isi', 'Status Jawaban', 'Tingkat kesulitan Soal'],
            [
                [1, 'SOAL', 'Q', 'Ibu kota Indonesia adalah...', '', 1],
                ['', 'JAWABAN', 'A', 'Bandung', 0, ''],
                ['', 'JAWABAN', 'A', 'Jakarta', 1, ''],
                ['', 'JAWABAN', 'A', 'Surabaya', 0, ''],
                ['', 'JAWABAN', 'A', 'Medan', 0, ''],
                ['', 'JAWABAN', 'A', 'Semarang', 0, ''],
                [2, 'SOAL', 'Q', '2 + 3 x 4 = ...', '', 1],
                ['', 'JAWABAN', 'A', '20', 0, ''],
                ['', 'JAWABAN', 'A', '14', 1, ''],
                ['', 'JAWABAN', 'A', '24', 0, ''],
                ['', 'JAWABAN', 'A', '11', 0, ''],
                ['', 'JAWABAN', 'A', '9', 0, ''],
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
            $rows = Sheet::soalTopik($file->getTempName(), $file->getClientName());
        } catch (\Throwable $e) {
            log_message('error', 'Import soal gagal: {m}', ['m' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Berkas tidak bisa dibaca: ' . $e->getMessage());
        }

        if ($rows === []) {
            return redirect()->back()->with('error', 'Format tidak dikenali. Tidak ditemukan baris SOAL dengan lima baris JAWABAN.');
        }

        $model = model(QuestionModel::class);
        $masuk = 0;
        $tolak = [];

        foreach ($rows as $soal) {
            $baris = (int) $soal['baris'];
            $opsi  = $soal['opsi'];
            $kunci = $soal['kunci'];

            if (trim($soal['teks']) === '') {
                $tolak[] = "Baris {$baris}: pertanyaan kosong.";
                continue;
            }
            if (count($opsi) !== 5) {
                $tolak[] = "Baris {$baris}: harus memiliki tepat 5 baris JAWABAN.";
                continue;
            }
            if (count($kunci) !== 1) {
                $tolak[] = count($kunci) === 0
                    ? "Baris {$baris}: belum ada jawaban benar (isi Status Jawaban dengan 1)."
                    : "Baris {$baris}: hanya boleh ada satu Status Jawaban bernilai 1.";
                continue;
            }

            $data = [
                'bank_id' => $bankId,
                'teks'    => $soal['teks'],
                'opsi_a'  => $opsi['A'],
                'opsi_b'  => $opsi['B'],
                'opsi_c'  => $opsi['C'],
                'opsi_d'  => $opsi['D'],
                'opsi_e'  => $opsi['E'],
                'kunci'   => $kunci[0],
                'bobot'   => 1,
            ];

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
