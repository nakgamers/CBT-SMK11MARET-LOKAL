<?php

/**
 * Helper CBT — dipakai di view & controller.
 */

if (! function_exists('cbt_sekolah')) {
    function cbt_sekolah(): string
    {
        return (string) (env('cbt.namaSekolah') ?: 'SEKOLAH');
    }
}

if (! function_exists('cbt_app')) {
    function cbt_app(): string
    {
        return (string) (env('cbt.namaAplikasi') ?: 'CBT Ujian Online');
    }
}

if (! function_exists('cbt_moto')) {
    function cbt_moto(): string
    {
        return (string) (env('cbt.motoSekolah') ?: '');
    }
}

if (! function_exists('cbt_logo')) {
    /**
     * URL logo sekolah. Bila berkasnya belum diunggah, kembalikan string kosong
     * supaya view bisa jatuh ke inisial huruf — jangan sampai muncul ikon rusak.
     *
     * @param string $ukuran '' (512px), '192', atau '64'
     */
    function cbt_logo(string $ukuran = ''): string
    {
        $nama  = 'logo' . ($ukuran !== '' ? '-' . $ukuran : '') . '.png';
        $absis = FCPATH . 'assets/img/' . $nama;

        return is_file($absis) ? base_url('assets/img/' . $nama) : '';
    }
}

if (! function_exists('cbt_url_login')) {
    /**
     * Alamat login yang dicetak di kartu siswa.
     * baseURL sudah mengikuti host yang diakses admin, jadi membuka halaman
     * kartu lewat IP LAN otomatis mencetak IP itu. Override manual lewat
     * cbt.alamatLogin di .env bila sekolah punya domain sendiri.
     */
    function cbt_url_login(): string
    {
        $manual = trim((string) (env('cbt.alamatLogin') ?: ''));

        return $manual !== '' ? $manual : site_url('login');
    }
}

if (! function_exists('tgl_id')) {
    /** 2026-03-01 07:30:00 -> "1 Mar 2026, 07:30" */
    function tgl_id(?string $sql, bool $jam = true): string
    {
        if (! $sql) {
            return '-';
        }
        $ts = strtotime($sql);
        if (! $ts) {
            return '-';
        }
        $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts)
            . ($jam ? ', ' . date('H:i', $ts) : '');
    }
}

if (! function_exists('badge_status')) {
    function badge_status(string $status): string
    {
        return '<span class="badge badge-' . esc($status, 'attr') . '">'
            . esc(\App\Models\ExamModel::labelStatus($status)) . '</span>';
    }
}

if (! function_exists('flash_alerts')) {
    /** Render flashdata error/success/info sekali pakai. */
    function flash_alerts(): string
    {
        $out = '';
        $map = ['error' => 'alert-error', 'success' => 'alert-ok', 'info' => 'alert-info', 'warning' => 'alert-warn'];
        foreach ($map as $key => $cls) {
            $msg = session()->getFlashdata($key);
            if (! $msg) {
                continue;
            }
            if (is_array($msg)) {
                $out .= '<div class="alert ' . $cls . '"><ul>';
                foreach ($msg as $m) {
                    $out .= '<li>' . esc($m) . '</li>';
                }
                $out .= '</ul></div>';

                continue;
            }
            $out .= '<div class="alert ' . $cls . '">' . esc($msg) . '</div>';
        }

        return $out;
    }
}
