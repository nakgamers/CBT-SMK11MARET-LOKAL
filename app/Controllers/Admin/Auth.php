<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminModel;

class Auth extends BaseController
{
    public function login()
    {
        if (session()->get('admin_id')) {
            return redirect()->to(site_url('admin'));
        }

        return view('auth/login_admin', [
            'title'   => 'Login Admin',
            'heading' => 'Panel Admin',
            'sub'     => cbt_app(),
            'icon'    => 'A',
        ]);
    }

    public function attempt()
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $admin = model(AdminModel::class)->findByUsername($username);

        if (! $admin || ! password_verify($password, $admin['password_hash'])) {
            return redirect()->back()->with('error', 'Username atau password salah.')->withInput();
        }

        session()->regenerate();
        session()->set([
            'admin_id'   => (int) $admin['id'],
            'admin_nama' => $admin['nama'],
        ]);

        return redirect()->to(site_url('admin'));
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to(site_url('admin/login'))->with('success', 'Anda telah keluar.');
    }
}
