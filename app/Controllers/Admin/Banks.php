<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\BankModel;
use App\Models\ExamModel;

class Banks extends BaseController
{
    public function index()
    {
        return view('admin/bank', [
            'title' => 'Bank Soal',
            'banks' => model(BankModel::class)->withJumlahSoal(),
        ]);
    }

    public function simpan()
    {
        $model = model(BankModel::class);
        $id    = (int) $this->request->getPost('id');
        $data  = [
            'nama'       => trim((string) $this->request->getPost('nama')),
            'mapel'      => trim((string) $this->request->getPost('mapel')),
            'keterangan' => trim((string) $this->request->getPost('keterangan')) ?: null,
        ];

        $ok = $id ? $model->update($id, $data) : $model->insert($data);
        if ($ok === false) {
            return redirect()->back()->with('error', $model->errors())->withInput();
        }

        return redirect()->to(site_url('admin/bank'))
            ->with('success', $id ? 'Bank soal diperbarui.' : 'Bank soal dibuat.');
    }

    public function hapus(int $id)
    {
        // FK exams.bank_id RESTRICT — cek dulu supaya pesannya jelas, bukan SQL error
        $dipakai = model(ExamModel::class)->where('bank_id', $id)->countAllResults();
        if ($dipakai > 0) {
            return redirect()->back()->with('error', "Bank soal dipakai oleh {$dipakai} ujian. Hapus ujiannya lebih dulu.");
        }

        model(BankModel::class)->delete($id);

        return redirect()->back()->with('success', 'Bank soal & seluruh soalnya dihapus.');
    }
}
