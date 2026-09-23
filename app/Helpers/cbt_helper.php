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

if (! function_exists('cbt_css_url')) {
    /**
     * URL CSS dengan parameter versi = waktu modifikasi file.
     *
     * nginx aaPanel memakai expires 7d untuk aset statis. Saat CSS diubah,
     * browser yang sudah pernah memuatnya tetap memakai versi lama sampai
     * cache kedaluwarsa — rumus baru terlihat tidak terformat. Parameter ?v
     * membuat URL unik setiap kali CSS berubah, jadi cache bypass otomatis.
     */
    function cbt_css_url(string $berkas = 'assets/css/app.css'): string
    {
        $absis = FCPATH . $berkas;
        $versi = is_file($absis) ? (string) filemtime($absis) : '0';

        return base_url($berkas) . '?v=' . $versi;
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

if (! function_exists('cbt_bersihkan_office')) {
    /**
     * Karakter dari Word/Excel (math autocorrect) mempermainkan rendering teks:
     * U+2212 minus tipis tampak seperti tanda kurang biasa tapi berperilaku beda
     * di font, U+00A0 spasi non-breaking menyebabkan teks tidak membungkus,
     * kutip melengkung mematahkan filter XSS yang mengharapkan tanda lurus.
     * Ubah semuanya ke versi ASCII yang dirender font sistem secara konsisten.
     */
    function cbt_bersihkan_office(string $teks): string
    {
        $map = [
            "\u{2212}"  => '-',   // minus tipis
            "\u{2010}"  => '-',   // hyphen
            "\u{2011}"  => '-',   // non-breaking hyphen
            "\u{2012}"  => '-',   // figure dash
            "\u{2013}"  => '-',   // en dash
            "\u{2014}"  => '-',   // em dash
            "\u{00A0}"  => ' ',   // spasi non-breaking
            "\u{2007}"  => ' ',   // figure space
            "\u{202F}"  => ' ',   // narrow no-break space
            "\u{200B}"  => '',    // zero-width space
            "\u{FEFF}"  => '',    // BOM
            "\u{2018}"  => "'",   // single quote kiri
            "\u{2019}"  => "'",   // single quote kanan
            "\u{201A}"  => "'",
            "\u{201B}"  => "'",
            "\u{201C}"  => '"',   // double quote kiri
            "\u{201D}"  => '"',   // double quote kanan
            "\u{201E}"  => '"',
            "\u{2026}"  => '...', // elipsis
            "\u{02C6}"  => '^',   // modifier letter circumflex
            "\u{00B7}"  => '*',   // middle dot -> kali
            "\u{00D7}"  => '*',   // multiplication sign -> kali
            "\u{2217}"  => '*',
            "\u{00F7}"  => '/',   // division sign
            "\u{2215}"  => '/',   // division slash
            "\u{2264}"  => '<=',
            "\u{2265}"  => '>=',
            "\u{2260}"  => '!=',
            "\u{221E}"  => '~',
            "\u{2208}"  => ' in ',
            "\u{222A}"  => ' u ',
            "\u{2229}"  => ' n ',
            "\u{B0}"    => ' derajat ',
        ];

        $teks = strtr($teks, $map);
        // "\r\n" & "\r" -> "\n"
        $teks = str_replace(["\r\n", "\r"], "\n", $teks);
        // Lepas baris-baris kosong yang tidak berguna
        $teks = preg_replace('/\n{3,}/', "\n\n", $teks);

        // Simbol superscript/subscript yang sering dipakai soal MTK & kimia
        $super = ["\u{00B2}" => '^2', "\u{00B3}" => '^3', "\u{00B9}" => '^1'];
        $teks = strtr($teks, $super);
        $sub = ["\u{2080}" => '_0', "\u{2081}" => '_1', "\u{2082}" => '_2', "\u{2083}" => '_3', "\u{2084}" => '_4'];
        $teks = strtr($teks, $sub);

        return trim($teks);
    }
}

if (! function_exists('cbt_rumus_html')) {
    /**
     * Render notasi natural jadi HTML rumus. Dipanggil SESUDAH esc(), jadi
     * karakter < > & di input sudah jadi entity &lt; &gt; &amp; — itulah
     * sebabnya simbol dibandingkan dalam bentuk entity, bukan ASCII mentah.
     *
     * Notasi yang dipakai:
     *   sqrt(x)         -> akar
     *   x^2, x^(n+1)    -> pangkat
     *   x_1, x_(n)      -> subscript
     *   (a+b)/(c+d)     -> pecahan tumpuk (pembilang di atas penyebut)
     *   a/b             -> tetap satu baris
     *   <= >= != +- *   -> simbol matematika standar
     */
    function cbt_rumus_html(string $teks): string
    {
        // 1) akar: sqrt(...) — [^()] cukup karena guru jarang menumpuk akar
        $teks = preg_replace_callback('/sqrt\(([^()]*)\)/', static function ($m) {
            return '<span class="mtk-akar">&radic;<span class="mtk-akar-isi">' . $m[1] . '</span></span>';
        }, $teks);

        // 2) pangkat: x^(pemangkat) atau x^2 (satu token)
        $teks = preg_replace_callback('/\^(\([^()]*\)|[0-9A-Za-z.+-]+)/', static function ($m) {
            $isi = $m[1];
            if ($isi[0] === '(') {
                $isi = substr($isi, 1, -1);
            }
            return '<sup>' . $isi . '</sup>';
        }, $teks);

        // 3) subscript: x_(i) atau x_1
        $teks = preg_replace_callback('/_(\([^()]*\)|[0-9A-Za-z.+-]+)/', static function ($m) {
            $isi = $m[1];
            if ($isi[0] === '(') {
                $isi = substr($isi, 1, -1);
            }
            return '<sub>' . $isi . '</sub>';
        }, $teks);

        // 4) pecahan tumpuk: (pembilang)/(penyebut)
        $teks = preg_replace_callback('/\(([^()]*)\)\/\(([^()]*)\)/', static function ($m) {
            return '<span class="mtk-frac"><span class="mtk-atas">' . $m[1] . '</span>'
                . '<span class="mtk-bawah">' . $m[2] . '</span></span>';
        }, $teks);

        // 5) simbol: input sudah di-esc(), jadi cocokkan bentuk entity-nya
        $simbol = [
            '&lt;='   => '&le;',
            '&gt;='   => '&ge;',
            '&lt;&gt;' => '&ne;',
            '!='      => '&ne;',
            '+-'      => '&plusmn;',
            '*'       => '&times;',
            '-&gt;'   => '&rarr;',
        ];
        $teks = strtr($teks, $simbol);

        return $teks;
    }
}

if (! function_exists('cbt_render_soal')) {
    /**
     * Satu pintu render teks soal / opsi jawaban.
     *
     * 1. bersihkan karakter Word/Excel
     * 2. esc() supaya aman dari XSS
     * 3. render notasi rumus (sqrt, ^, /, _)
     * 4. baris baru -> <br>
     *
     * Pemanggil WAJIB memakai ini, bukan nl2br(esc(...)) langsung.
     */
    function cbt_render_soal(?string $teks, bool $rumus = true): string
    {
        $teks = cbt_bersihkan_office((string) $teks);
        $teks = esc($teks);
        if ($rumus) {
            $teks = cbt_rumus_html($teks);
        }

        return nl2br($teks);
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
