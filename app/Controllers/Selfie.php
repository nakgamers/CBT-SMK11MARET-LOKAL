<?php

namespace App\Controllers;

use App\Libraries\SelfieStore;
use App\Models\ExamModel;
use App\Models\SelfieModel;

/**
 * Absen selfie untuk siswa: halaman kamera + endpoint upload AJAX.
 * Foto dikompres di browser (canvas) SEBELUM dikirim, lalu diproses
 * ulang di server (resize + thumbnail) — DB hanya menampung metadata.
 */
class Selfie extends BaseController
{
    /** Ambil + validasi konteks ujian milik siswa ini. */
    private function konteks(int $examId): ?array
    {
        $siswa = $this->siswa();
        if (! $siswa) {
            return null;
        }
        $exam = model(ExamModel::class)->withBank($examId);
        if (! $exam || (int) $exam['aktif'] !== 1 || ExamModel::statusWaktu($exam, time()) !== 'berlangsung') {
            return null;
        }
        if ($exam['kelas'] !== '*' && ! in_array($siswa['kelas'], array_map('trim', explode(',', (string) $exam['kelas'])), true)) {
            return null;
        }
        return [$siswa, $exam];
    }

    /** Halaman kamera selfie. */
    public function index(int $examId)
    {
        $ctx = $this->konteks($examId);
        if ($ctx === null) {
            return redirect()->to(site_url('siswa'))->with('error', 'Ujian tidak tersedia untuk absen selfie.');
        }
        [$siswa, $exam] = $ctx;

        // sudah pernah absen -> langsung boleh mulai
        $sudah = model(SelfieModel::class)->untukSiswa($examId, (int) $siswa['id']);

        return view('siswa/selfie', [
            'title' => 'Absen Selfie — ' . $exam['nama'],
            'siswa' => $siswa,
            'exam'  => $exam,
            'sudah' => $sudah,
        ]);
    }

    /**
     * Upload selfie (AJAX, multipart). Response JSON.
     * Upload ulang menimpa: file lama dihapus, DB tetap 1 baris/siswa/ujian.
     */
    public function upload(int $examId)
    {
        $ctx = $this->konteks($examId);
        if ($ctx === null) {
            return $this->json(['ok' => false, 'error' => 'Ujian tidak tersedia.'], 403);
        }
        [$siswa, $exam] = $ctx;

        $file = $this->request->getFile('selfie');
        if ($file === null || ! $file->isValid()) {
            return $this->json(['ok' => false, 'error' => 'Foto tidak diterima.'], 422);
        }

        $store = new SelfieStore();
        $model = model(SelfieModel::class);
        $lama  = $model->untukSiswa($examId, (int) $siswa['id']);

        try {
            $meta = $store->simpan($file, $examId, (int) $siswa['id']);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $data = [
            'exam_id'    => $examId,
            'student_id' => (int) $siswa['id'],
            'file_path'  => $meta['full'],
            'thumb_path' => $meta['thumb'],
            'file_size'  => $meta['size'],
            'thumb_size' => $meta['thumb_size'],
            'width'      => $meta['width'],
            'height'     => $meta['height'],
            'hash'       => $meta['hash'],
            'ip'         => $this->request->getIPAddress(),
        ];

        if ($lama) {
            $model->update((int) $lama['id'], $data);
            // file lama beda nama -> bersihkan
            if ($lama['file_path'] !== $meta['full']) {
                $store->hapus($lama['file_path'], $lama['thumb_path']);
            }
        } else {
            $model->insert($data);
        }

        return $this->json([
            'ok'    => true,
            'kb'    => round($meta['size'] / 1024),
            'thumb' => site_url('siswa/absen/' . $examId . '/lihat/' . $meta['thumb']),
        ]);
    }

    /**
     * Sajikan foto/thumb milik siswa sendiri (satu-satunya akses non-admin).
     * Query string ?t=hash membuat browser boleh cache tanpa membocorkan
     * ke orang lain: path saja tidak bisa ditebak (mengandung hash konten).
     */
    public function lihat(int $examId, string $nama)
    {
        $ctx = $this->konteks($examId);
        if ($ctx === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        [$siswa] = $ctx;

        // hanya file milik siswa ini sendiri
        if (! preg_match('/^' . preg_quote($examId . '_' . $siswa['id'] . '_', '/') . '[a-f0-9]+(\.thumb)?\.jpg$/i', $nama)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $store = new SelfieStore();
        $f = $store->baca($nama);
        if ($f === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setContentType($f['mime'])
            ->noCache()
            ->setBody(file_get_contents($f['path']));
    }

    private function json(array $d, int $status = 200)
    {
        return $this->response->setJSON($d)->setStatusCode($status);
    }
}
