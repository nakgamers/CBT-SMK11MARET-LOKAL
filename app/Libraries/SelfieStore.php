<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Penyimpanan foto absen selfie.
 *
 * Prinsip optimasi:
 *  - File disimpan di luar public/ (writable/uploads/selfie) sehingga
 *    TIDAK bisa diakses langsung — hanya lewat controller yang memeriksa
 *    sesi admin. Ini juga melindungi data wajah siswa.
 *  - Foto penuh di-resize maks 1280px JPEG ~80% (umumnya 100-250KB),
 *    thumbnail 320px ~72% (umumnya 10-25KB). Galeri admin menarik
 *    thumbnail -> ringan; foto penuh hanya saat dibuka satu per satu.
 *  - Nama file dibuat sendiri (bukan nama upload): {exam}_{student}_{hash8}.jpg
 *    sehingga tidak bisa dipakai path traversal / double extension.
 */
class SelfieStore
{
    /** Batas keras ukuran upload mentah dari browser (byte). */
    public const MAX_UPLOAD_BYTES = 3 * 1024 * 1024; // 3 MB

    private const FULL_MAX_EDGE  = 1280;
    private const FULL_QUALITY   = 80;
    private const THUMB_MAX_EDGE = 320;
    private const THUMB_QUALITY  = 72;

    private string $root;

    public function __construct()
    {
        // writable/uploads/selfie — di-allow oleh .gitignore CI4 (uploads/*)
        $this->root = rtrim(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'selfie', DIRECTORY_SEPARATOR);
        if (! is_dir($this->root)) {
            mkdir($this->root, 0775, true);
        }
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Proses upload: validasi -> kompresi full + thumb -> simpan.
     *
     * @return array{full:string,thumb:string,size:int,thumb_size:int,width:int,height:int,hash:string}
     *         path relatif (terhadap root) + metadata
     */
    public function simpan(\CodeIgniter\HTTP\Files\UploadedFile $file, int $examId, int $studentId): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException('File tidak diterima dengan benar.');
        }
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('Foto terlalu besar (maks 3 MB).');
        }

        // validasi isi gambar sungguhan, bukan cuma ekstensi
        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('File harus berupa foto JPEG/PNG/WebP.');
        }

        $src = $this->loadFrom($file->getRealPath(), $info[2]);
        if ($src === null) {
            throw new RuntimeException('Foto tidak dapat dibaca.');
        }

        // rotasi otomatis sesuai EXIF (foto HP sering miring)
        $src = $this->rotateByExif($src, $file->getRealPath(), $info[2]);

        [$fw, $fh] = $this->scaledDims(imagesx($src), imagesy($src), self::FULL_MAX_EDGE);
        $full  = $this->render($src, $fw, $fh, self::FULL_QUALITY);

        [$tw, $th] = $this->scaledDims($fw, $fh, self::THUMB_MAX_EDGE);
        $thumb = $this->renderResampled($full, $tw, $th, self::THUMB_QUALITY);

        $hash = hash_file('sha256', $file->getRealPath());
        $base = sprintf('%d_%d_%s', $examId, $studentId, substr($hash, 0, 8));

        $fullRel  = $base . '.jpg';
        $thumbRel = $base . '.thumb.jpg';

        // tulis atomik-ish: file baru -> hapus milik lama dipanggil terpisah
        imagejpeg($full, $this->root . DIRECTORY_SEPARATOR . $fullRel, self::FULL_QUALITY);
        imagejpeg($thumb, $this->root . DIRECTORY_SEPARATOR . $thumbRel, self::THUMB_QUALITY);

        imagedestroy($src);
        imagedestroy($full);
        imagedestroy($thumb);

        clearstatcache(true, $this->root . DIRECTORY_SEPARATOR . $fullRel);

        return [
            'full'       => $fullRel,
            'thumb'      => $thumbRel,
            'size'       => (int) filesize($this->root . DIRECTORY_SEPARATOR . $fullRel),
            'thumb_size' => (int) filesize($this->root . DIRECTORY_SEPARATOR . $thumbRel),
            'width'      => $fw,
            'height'     => $fh,
            'hash'       => $hash,
        ];
    }

    /** Hapus file full+thumb dari path relatif (dipakai saat ganti/hapus record). */
    public function hapus(?string $fullRel, ?string $thumbRel): void
    {
        $this->hapusSatu($fullRel);
        $this->hapusSatu($thumbRel);
        // safety-net: thumb lama dengan pola turunan full
        if (is_string($fullRel)) {
            $this->hapusSatu(preg_replace('/\.jpg$/', '.thumb.jpg', $fullRel));
        }
    }

    /**
     * Baca file untuk disajikan (sudah divalidasi admin di controller).
     * Return ['path' => absolute, 'mime' => 'image/jpeg'] atau null.
     */
    public function baca(string $rel): ?array
    {
        // cegah traversal: hanya nama file datar .jpg
        if (! preg_match('/^[a-z0-9_]+\.thumb\.jpg$|^[a-z0-9_]+\.jpg$/i', $rel)) {
            return null;
        }
        $abs = $this->root . DIRECTORY_SEPARATOR . $rel;
        if (! is_file($abs)) {
            return null;
        }
        return ['path' => $abs, 'mime' => 'image/jpeg'];
    }

    // ---------------------------------------------------------------- internal

    private function loadFrom(string $abs, int $type)
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($abs),
            IMAGETYPE_PNG  => @imagecreatefrompng($abs),
            IMAGETYPE_WEBP => @imagecreatefromwebp($abs),
            default        => null,
        };
    }

    /** Scale-down proporsional agar sisi terpanjang = $maxEdge. */
    private function scaledDims(int $w, int $h, int $maxEdge): array
    {
        if ($w <= $maxEdge && $h <= $maxEdge) {
            return [$w, $h];
        }
        $r = $maxEdge / max($w, $h);
        return [max(1, (int) round($w * $r)), max(1, (int) round($h * $r))];
    }

    /** Resize dengan imagecopyresampled (lebih halus dari imagecopy). */
    private function render($src, int $tw, int $th, int $quality)
    {
        $dst = imagecreatetruecolor($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, imagesx($src), imagesy($src));
        return $dst;
    }

    private function renderResampled($src, int $tw, int $th, int $quality)
    {
        return $this->render($src, $tw, $th, $quality);
    }

    /** Terapkan rotasi EXIF (kamera HP menaruh orientasi di metadata). */
    private function rotateByExif($img, string $abs, int $type)
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($abs);
        $ori  = (int) ($exif['Orientation'] ?? 1);
        if ($ori <= 1) {
            return $img;
        }
        $rot = match ($ori) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($rot === 0) {
            return $img;
        }
        $out = imagerotate($img, $rot, 0);
        if ($out !== false) {
            imagedestroy($img);
            return $out;
        }
        return $img;
    }

    private function hapusSatu(?string $rel): void
    {
        if (! is_string($rel) || $rel === '') {
            return;
        }
        if (! preg_match('/^[a-z0-9_]+(\.thumb)?\.jpg$/i', $rel)) {
            return;
        }
        $abs = $this->root . DIRECTORY_SEPARATOR . $rel;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
