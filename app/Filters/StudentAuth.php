<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class StudentAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('student_id')) {
            return;
        }

        // Request AJAX (autosave) tidak boleh dapat halaman login sebagai balasan.
        if ($request->isAJAX()) {
            return service('response')->setStatusCode(401)->setJSON([
                'ok'    => false,
                'error' => 'Sesi berakhir, silakan login ulang.',
            ]);
        }

        return redirect()->to(site_url('login'))->with('error', 'Silakan login dengan kartu ujian.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
