<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminModel;

class Akun extends BaseController
{
    public function index()
    {
        $admin = model(AdminModel::class)->find((int) session('admin_id'));
        if (! $admin) {
            // akun dihapus dari DB sementara sesi masih hidup
            return redirect()->to(site_url('admin/logout'));
        }

        return view('admin/akun', [
            'title' => 'Ganti Password',
            'admin' => $admin,
        ]);
    }

    public function password()
    {
        $model = model(AdminModel::class);
        $admin = $model->find((int) session('admin_id'));
        if (! $admin) {
            return redirect()->to(site_url('admin/logout'));
        }

        $lama    = (string) $this->request->getPost('password_lama');
        $baru    = (string) $this->request->getPost('password_baru');
        $ulang   = (string) $this->request->getPost('password_ulang');

        // Password lama wajib benar: kalau tidak, siapa pun yang menemukan
        // komputer admin dalam keadaan login bisa mengunci pemilik aslinya.
        if (! password_verify($lama, $admin['password_hash'])) {
            return redirect()->back()->with('error', 'Password lama salah.');
        }

        if (mb_strlen($baru) < 8) {
            return redirect()->back()->with('error', 'Password baru minimal 8 karakter.');
        }

        if ($baru !== $ulang) {
            return redirect()->back()->with('error', 'Konfirmasi password tidak sama.');
        }

        if (password_verify($baru, $admin['password_hash'])) {
            return redirect()->back()->with('error', 'Password baru masih sama dengan yang lama.');
        }

        $model->update($admin['id'], ['password_hash' => password_hash($baru, PASSWORD_DEFAULT)]);

        // Ganti session id supaya sesi lama (mis. di komputer lab yang belum
        // ditutup) tidak lagi dipakai dengan password yang sudah diubah.
        session()->regenerate(true);

        return redirect()->to(site_url('admin/akun'))
            ->with('success', 'Password berhasil diubah. Gunakan password baru pada login berikutnya.');
    }
}
