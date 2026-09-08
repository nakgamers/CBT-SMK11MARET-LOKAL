<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AttemptModel;
use App\Models\BankModel;
use App\Models\ExamModel;
use App\Models\QuestionModel;
use App\Models\StudentModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $examModel = model(ExamModel::class);
        $exams     = $examModel->withRingkasan();

        $now      = time();
        $aktifKini = 0;
        foreach ($exams as &$e) {
            $e['status_waktu'] = ExamModel::statusWaktu($e, $now);
            if ($e['status_waktu'] === 'berlangsung') {
                $aktifKini++;
            }
        }
        unset($e);

        return view('admin/dashboard', [
            'title'   => 'Dashboard',
            'jumlah'  => [
                'siswa'    => model(StudentModel::class)->countAllResults(),
                'bank'     => model(BankModel::class)->countAllResults(),
                'soal'     => model(QuestionModel::class)->countAllResults(),
                'ujian'    => count($exams),
                'aktif'    => $aktifKini,
                'attempt'  => model(AttemptModel::class)->where('status', 'selesai')->countAllResults(),
            ],
            'terbaru' => array_slice($exams, 0, 6),
        ]);
    }
}
