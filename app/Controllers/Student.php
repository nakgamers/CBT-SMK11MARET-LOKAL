<?php

namespace App\Controllers;

use App\Models\AttemptModel;
use App\Models\ExamModel;

class Student extends BaseController
{
    public function index()
    {
        $siswa = $this->siswa();
        if (! $siswa) {
            return redirect()->to(site_url('logout'));
        }

        $exams   = model(ExamModel::class)->untukSiswa($siswa['kelas']);
        $attempt = model(AttemptModel::class)->petaSiswa((int) $siswa['id']);

        $now = time();
        foreach ($exams as &$e) {
            $e['status_waktu'] = ExamModel::statusWaktu($e, $now);
            $e['attempt']      = $attempt[(int) $e['id']] ?? null;
        }
        unset($e);

        return view('siswa/dashboard', [
            'title' => 'Daftar Ujian',
            'siswa' => $siswa,
            'exams' => $exams,
        ]);
    }
}
