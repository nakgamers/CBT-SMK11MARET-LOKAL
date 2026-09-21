<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Sheet;
use App\Models\StudentModel;

class Students extends BaseController
{
    public function index()
    {
        $model  = model(StudentModel::class);
        $kelas  = trim((string) $this->request->getGet('kelas'));
        $cari   = trim((string) $this->request->getGet('q'));

        if ($kelas !== '') {
            $model->where('kelas', $kelas);
        }
        if ($cari !== '') {
            $model->groupStart()->like('nama', $cari)->orLike('nis', $cari)->groupEnd();
        }

        return view('admin/siswa', [
            'title'       => 'Siswa & Kartu Login',
            'siswa'       => $model->orderBy('kelas')->orderBy('nama')->paginate(50),
            'pager'       => $model->pager,
            'daftarKelas' => model(StudentModel::class)->daftarKelas(),
            'filter'      => ['kelas' => $kelas, 'q' => $cari],
        ]);
    }

    public function simpan()
    {
        $model = model(StudentModel::class);
        $id    = (int) $this->request->getPost('id');

        $data = [
            'id'    => $id ?: null,
            'nis'   => trim((string) $this->request->getPost('nis')),
            'nama'  => trim((string) $this->request->getPost('nama')),
            'kelas' => strtoupper(trim((string) $this->request->getPost('kelas'))),
            'jk'    => $this->request->getPost('jk') === 'P' ? 'P' : 'L',
            'token' => strtoupper(trim((string) $this->request->getPost('token'))) ?: trim((string) $this->request->getPost('nis')),
            'aktif' => $this->request->getPost('aktif') ? 1 : 0,
        ];

        if ($id) {
            $ok = $model->update($id, $data);
        } else {
            unset($data['id']);
            $ok = $model->insert($data);
        }

        if ($ok === false) {
            return redirect()->back()->with('error', $model->errors())->withInput();
        }

        return redirect()->to(site_url('admin/siswa'))
            ->with('success', $id ? 'Data siswa diperbarui.' : 'Siswa baru ditambahkan.');
    }

    public function hapus(int $id)
    {
        model(StudentModel::class)->delete($id);

        return redirect()->back()->with('success', 'Siswa dihapus beserta riwayat ujiannya.');
    }

    public function resetToken(int $id)
    {
        // kebijakan saat ini: token = NIS (reset cukup samakan kembali)
        $siswa = model(StudentModel::class)->find($id);
        if (!$siswa) {
            return redirect()->back()->with('error', 'Siswa tidak ditemukan.');
        }
        model(StudentModel::class)->update($id, ['token' => $siswa['nis']]);

        return redirect()->back()->with('success', 'Password disamakan dengan NIS: ' . $siswa['nis']);
    }

    public function template()
    {
        Sheet::unduhTemplate('template-siswa.xlsx', ['nis', 'nama', 'kelas', 'jk'], [
            ['2024001', 'Ahmad Fauzi', 'XII RPL 1', 'L'],
            ['2024002', 'Siti Aminah', 'XII RPL 1', 'P'],
        ]);
    }

    public function import()
    {
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
            log_message('error', 'Import siswa gagal: {m}', ['m' => $e->getMessage()]);

            return redirect()->back()->with('error', 'Berkas tidak bisa dibaca: ' . $e->getMessage());
        }

        $model  = model(StudentModel::class);
        $masuk  = 0;
        $ubah   = 0;
        $tolak  = [];

        foreach ($rows as $n => $r) {
            $baris = $n + 2; // +1 header, +1 basis-1
            $nis   = $r[0] ?? '';
            $nama  = $r[1] ?? '';
            $kelas = strtoupper($r[2] ?? '');
            $jk    = strtoupper(substr($r[3] ?? 'L', 0, 1)) === 'P' ? 'P' : 'L';

            if ($nis === '' || $nama === '' || $kelas === '') {
                $tolak[] = "Baris {$baris}: nis/nama/kelas kosong.";

                continue;
            }

            $ada = $model->where('nis', $nis)->first();
            if ($ada) {
                // update data, token lama dipertahankan supaya kartu tercetak tetap sah
                $model->update($ada['id'], ['nama' => $nama, 'kelas' => $kelas, 'jk' => $jk]);
                $ubah++;

                continue;
            }

            $ok = $model->insert([
                'nis'   => $nis,
                'nama'  => $nama,
                'kelas' => $kelas,
                'jk'    => $jk,
                'token' => $nis, // kebijakan: token = NIS
                'aktif' => 1,
            ]);

            if ($ok === false) {
                $tolak[] = "Baris {$baris}: " . implode(' ', $model->errors());

                continue;
            }
            $masuk++;
        }

        $pesan = "Import selesai: {$masuk} siswa baru, {$ubah} diperbarui.";
        if ($tolak !== []) {
            return redirect()->to(site_url('admin/siswa'))
                ->with('success', $pesan)
                ->with('error', array_slice($tolak, 0, 15));
        }

        return redirect()->to(site_url('admin/siswa'))->with('success', $pesan);
    }

    /** Lembar kartu login siap cetak. */
    public function kartu()
    {
        $model = model(StudentModel::class);
        $kelas = trim((string) $this->request->getGet('kelas'));
        if ($kelas !== '') {
            $model->where('kelas', $kelas);
        }

        return view('admin/kartu', [
            'title'       => 'Kartu Login Siswa',
            'siswa'       => $model->where('aktif', 1)->orderBy('kelas')->orderBy('nama')->findAll(),
            'kelas'       => $kelas,
            'daftarKelas' => model(StudentModel::class)->daftarKelas(),
        ]);
    }
}
