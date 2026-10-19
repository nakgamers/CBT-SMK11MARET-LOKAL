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
            $e['selesai']      = $e['attempt'] && $e['attempt']['status'] === 'selesai';
            $e['kelompok']     = $e['selesai']
                ? 2
                : ($e['status_waktu'] === 'berlangsung' ? 0 : ($e['status_waktu'] === 'belum' ? 1 : 2));
        }
        unset($e);

        usort($exams, static function (array $a, array $b): int {
            if ($a['kelompok'] !== $b['kelompok']) {
                return $a['kelompok'] <=> $b['kelompok'];
            }

            $waktuA = strtotime((string) $a['mulai_at']);
            $waktuB = strtotime((string) $b['mulai_at']);

            return $a['kelompok'] === 1
                ? $waktuA <=> $waktuB
                : $waktuB <=> $waktuA;
        });

        // Penanda simulasi yang tersedia di schema/data lama: kelas UJI COBA.
        $stat = db_connect()->query(
            'SELECT COUNT(DISTINCT e.id) AS total,
                    COUNT(DISTINCT CASE WHEN a.status = "selesai" THEN e.id END) AS selesai
             FROM exams e
             LEFT JOIN attempts a
               ON a.exam_id = e.id
              AND a.student_id = ?
              AND a.status = "selesai"
             WHERE e.aktif = 1
               AND (e.kelas = "*" OR FIND_IN_SET(?, REPLACE(e.kelas, ", ", ",")) > 0)
               AND UPPER(TRIM(e.kelas)) <> "UJI COBA"',
            [(int) $siswa['id'], (string) $siswa['kelas']]
        )->getRowArray();

        return view('siswa/dashboard', [
            'title'        => 'Daftar Ujian',
            'siswa'        => $siswa,
            'exams'        => $exams,
            'progres'      => [
                'selesai' => (int) ($stat['selesai'] ?? 0),
                'total'   => (int) ($stat['total'] ?? 0),
            ],
        ]);
    }
}
