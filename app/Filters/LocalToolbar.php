<?php

namespace App\Filters;

use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Debug Toolbar hanya untuk akses langsung dari laptop.
 *
 * Dua alasan toolbar tidak boleh ikut keluar lewat tunnel:
 *
 * 1. Keamanan. Endpoint `?debugbar_time=<ts>` menyajikan query SQL, isi sesi,
 *    dan path berkas kepada siapa pun yang memegang URL tunnel.
 * 2. Kebisingan. Loader toolbar mem-polling endpoint itu terus-menerus; koneksi
 *    yang ditutup di tengah jalan membuat cloudflared membanjiri log dengan
 *    "Incoming request ended abruptly: context canceled".
 *
 * REMOTE_ADDR tidak bisa dipakai membedakan, sebab cloudflared menghubungi
 * origin dari 127.0.0.1 juga. Yang membedakan adalah header yang hanya ada bila
 * permintaan melewati proxy/CDN.
 */
class LocalToolbar extends DebugToolbar
{
    /** Header yang menandakan permintaan datang lewat tunnel/proxy. */
    private const HEADER_PROXY = [
        'HTTP_X_FORWARDED_PROTO',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED_HOST',
        'HTTP_CF_CONNECTING_IP',
        'HTTP_CF_RAY',
    ];

    /**
     * Apakah permintaan ini datang lewat proxy/tunnel?
     *
     * Dipakai juga oleh app/Config/Events.php: endpoint `?debugbar` dan
     * `?debugbar_time` dilayani pada event pre_system, jauh sebelum filter
     * berjalan, jadi filter saja tidak cukup untuk menutupnya.
     */
    public static function lewatProxy(): bool
    {
        foreach (self::HEADER_PROXY as $h) {
            if (($_SERVER[$h] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (self::lewatProxy()) {
            return null;
        }

        return parent::after($request, $response, $arguments);
    }
}
