<?php

namespace App\Controllers;

/** TEMPORER — diagnostik koneksi DB produksi. Hapus segera setelah dipakai. */
class Debug extends BaseController
{
    public function db()
    {
        $db = \Config\Database::connect();

        $m1 = new \App\Models\StudentModel();
        $total = $m1->countAllResults();

        $m2 = new \App\Models\StudentModel();
        $row = $m2->where('nis', '2024001')->first();

        $m3 = new \App\Models\StudentModel();
        $byCard = $m3->findByCard('2024001', '2024001');

        return $this->response->setJSON([
            'env'       => ENVIRONMENT,
            'db'        => $db->database,
            'host'      => $db->hostname,
            'pid'       => getmypid() . '@' . gethostname(),
            'total'     => $total,
            'row_aktif' => $row['aktif'] ?? null,
            'row_token' => $row['token'] ?? null,
            'by_card'   => (bool) $byCard,
            'gd'        => function_exists('imagecreatefromjpeg') ? 'jpeg-ok' : 'EXT:' . implode(',', array_values(array_filter(['gd','mysqli','zip','intl','fileinfo'], 'extension_loaded'))),
            'log'       => @file_get_contents(WRITEPATH . 'dbg-attempt.log') ?: '(kosong)',
        ]);
    }
}
