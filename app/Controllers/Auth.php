<?php

namespace App\Controllers;

use App\Models\StudentModel;

class Auth extends BaseController
{
    public function login()
    {
        if (session()->get('student_id')) {
            return redirect()->to(site_url('siswa'));
        }

        return view('auth/login_siswa', [
            'title'   => 'Login Siswa',
            'heading' => 'Login Ujian',
            'sub'     => cbt_sekolah(),
        ]);
    }

    public function attempt()
    {
        $nis   = trim((string) $this->request->getPost('nis'));
        // token = NIS (berisi titik, mis. 24.7411): buang spasi & samakan huruf besar,
        // tapi JANGAN hapus tanda baca lain — titik adalah bagian sah NIS.
        $token = strtoupper(str_replace(' ', '', trim((string) $this->request->getPost('token'))));

        if ($nis === '' || $token === '') {
            return redirect()->back()->with('error', 'NIS dan token wajib diisi.')->withInput();
        }

        $siswa = model(StudentModel::class)->findByCard($nis, $token);

        // TEMP DIAG — hapus setelah selesai
        $dbug = \Config\Database::connect();
        $cNis  = (new \App\Models\StudentModel())->where('nis', $nis)->countAllResults();
        $cTok  = (new \App\Models\StudentModel())->where('nis', $nis)->where('token', $token)->countAllResults();
        $cAkt  = (new \App\Models\StudentModel())->where('nis', $nis)->where('token', $token)->where('aktif', 1)->countAllResults();
        @file_put_contents(
            WRITEPATH . 'dbg-attempt.log',
            date('H:i:s') . " nis=[{$nis}] tok=[{$token}] found=" . ($siswa ? 'Y' : 'N')
                . ' cNis=' . $cNis . ' cTok=' . $cTok . ' cAkt=' . $cAkt
                . ' hex=' . bin2hex($nis) . '/' . bin2hex($token)
                . ' db=' . $dbug->database . ' sql=' . (string) $dbug->getLastQuery() . "\n",
            FILE_APPEND
        );

        // fallback toleran titik: siswa mengetik "247411" padahal NIS "24.7411"
        if (! $siswa) {
            $nisL  = str_replace('.', '', $nis);
            $tokL  = str_replace('.', '', $token);
            $siswa = model(StudentModel::class)
                ->where("REPLACE(nis,'.','')", $nisL)
                ->where("REPLACE(token,'.','')", $tokL)
                ->where('aktif', 1)
                ->first();
        }

        if (! $siswa) {
            // pesan digabung: jangan bocorkan NIS mana yang valid
            return redirect()->back()->with('error', 'NIS atau token salah, atau akun dinonaktifkan.')->withInput();
        }

        session()->regenerate();
        session()->set([
            'student_id'   => (int) $siswa['id'],
            'student_nama' => $siswa['nama'],
        ]);

        return redirect()->to(site_url('siswa'));
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to(site_url('login'))->with('success', 'Anda telah keluar.');
    }
}
