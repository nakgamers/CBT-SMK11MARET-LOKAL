<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\SelfieStore;
use App\Models\ExamModel;
use App\Models\SelfieModel;
use App\Models\StudentModel;

/**
 * Galeri absen selfie untuk admin.
 *
 * Halaman galeri hanya menarik THUMBNAIL (10-25KB/gambar, lazy-load),
 * foto penuh dimuat saat admin membuka lightbox satu per satu.
 * File berada di luar public/ dan disajikan lewat controller ini
 * dengan pemeriksaan sesi admin.
 */
class Selfie extends BaseController
{
    public function index()
    {
        $f = [
            'exam_id' => (int) ($this->request->getGet('ujian') ?? 0),
            'kelas'   => trim((string) ($this->request->getGet('kelas') ?? '')),
            'tanggal' => trim((string) ($this->request->getGet('tanggal') ?? '')),
            'q'       => trim((string) ($this->request->getGet('q') ?? '')),
        ];
        if ($f['tanggal'] !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['tanggal'])) {
            $f['tanggal'] = '';
        }

        $page  = max(1, (int) ($this->request->getGet('hal') ?? 1));
        $per   = 48;
        $model = model(SelfieModel::class);
        [$rows, $total] = $model->galeri($f, $per, ($page - 1) * $per);

        return view('admin/selfie', [
            'title'   => 'Absen Selfie',
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int) ceil($total / $per),
            'f'       => $f,
            'exams'   => model(ExamModel::class)->orderBy('mulai_at', 'DESC')->findAll(),
            'kelas'   => array_column(model(StudentModel::class)->distinct()->select('kelas')->orderBy('kelas')->findAll(), 'kelas'),
            'totalKB' => round($model->totalByte() / 1024),
        ]);
    }

    /** Sajikan foto/thumb untuk admin (satu-satunya jalur baca file). */
    public function lihat(int $id, string $jenis = 'thumb')
    {
        $row = model(SelfieModel::class)->find($id);
        if (! $row) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $rel = $jenis === 'full' ? $row['file_path'] : $row['thumb_path'];

        $f = (new SelfieStore())->baca((string) $rel);
        if ($f === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // nama file mengandung hash konten -> aman di-cache browser lama-lama
        return $this->response
            ->setContentType($f['mime'])
            ->setCache(['max_age' => 86400, 'last_modified' => '@' . filemtime($f['path'])])
            ->setBody(file_get_contents($f['path']));
    }

    /** Hapus satu record + filenya. */
    public function hapus(int $id)
    {
        $model = model(SelfieModel::class);
        $row   = $model->find($id);
        if ($row) {
            (new SelfieStore())->hapus($row['file_path'], $row['thumb_path']);
            $model->delete($id);
            return redirect()->back()->with('success', 'Foto absen dihapus.');
        }
        return redirect()->back()->with('error', 'Data tidak ditemukan.');
    }

    /** Hapus semua selfie satu ujian (record + file). */
    public function hapusUjian(int $examId)
    {
        $model = model(SelfieModel::class);
        $rows  = $model->where('exam_id', $examId)->findAll();
        $store = new SelfieStore();
        foreach ($rows as $r) {
            $store->hapus($r['file_path'], $r['thumb_path']);
        }
        $model->where('exam_id', $examId)->delete();
        return redirect()->to(site_url('admin/absen?ujian=' . $examId))
            ->with('success', count($rows) . ' foto absen ujian dihapus.');
    }
}
