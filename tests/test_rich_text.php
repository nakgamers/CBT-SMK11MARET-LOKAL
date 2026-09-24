<?php

declare(strict_types=1);

// Self-check minimal untuk editor rich text soal. Jalankan:
// php tests/test_rich_text.php

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function base_url(string $path = ''): string
{
    return 'http://cbt.test/' . ltrim($path, '/');
}

require __DIR__ . '/../app/Helpers/cbt_helper.php';

function same(string $expected, string $actual, string $name): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "GAGAL {$name}\nEXPECTED: {$expected}\nACTUAL  : {$actual}\n");
        exit(1);
    }
    echo "OK {$name}\n";
}

function yes(bool $condition, string $name): void
{
    if (! $condition) {
        fwrite(STDERR, "GAGAL {$name}\n");
        exit(1);
    }
    echo "OK {$name}\n";
}

// Regresi terpenting: soal lama harus tetap dirender identik.
$lama = 'Nilai (5x-1)/(x+4) dan x^2';
$sebelum = nl2br(cbt_rumus_html(esc(cbt_bersihkan_office($lama))));
same($sebelum, cbt_render_soal($lama), 'soal lama tidak berubah');
yes(! cbt_soal_html($lama), 'teks lama bukan rich text');

// Rich text baru memakai marker internal, lalu hanya HTML whitelist yang lolos.
$input = '<p>Diketahui <strong>f(x)</strong> = x<sup>2</sup></p>'
    . '<p><img src="/uploads/soal-inline/rumus-abc.png" onerror="alert(1)" style="width:9999px"></p>'
    . '<script>alert(1)</script><a href="javascript:alert(1)">jahat</a>';
$tersimpan = cbt_siapkan_html_soal($input);
yes(cbt_soal_html($tersimpan), 'marker rich text dikenali');
$render = cbt_render_soal($tersimpan);
yes(str_contains($render, '<strong>f(x)</strong>'), 'format tebal dipertahankan');
yes(str_contains($render, '<sup>2</sup>'), 'superscript dipertahankan');
yes(str_contains($render, 'src="/uploads/soal-inline/rumus-abc.png"'), 'gambar lokal dipertahankan');
yes(! str_contains(strtolower($render), '<script'), 'script dibuang');
yes(! str_contains(strtolower($render), 'onerror'), 'event handler dibuang');
yes(! str_contains(strtolower($render), 'javascript:'), 'javascript URL dibuang');
yes(! str_contains(strtolower($render), 'style='), 'style Word dibuang');

// URL eksternal dan file:// tidak boleh lolos ke perangkat siswa.
$jahat = cbt_render_soal(cbt_siapkan_html_soal(
    '<img src="https://example.com/a.png"><img src="file:///C:/rumus.png"><img src="data:image/png;base64,AAAA">'
));
yes(! str_contains($jahat, '<img'), 'gambar nonlokal dan data URI mentah ditolak');

// Tag format yang dibutuhkan guru tetap ada; sampah mso hilang.
$word = '<div class="MsoNormal" style="font-family:Calibri"><b>Tebal</b><i>Miring</i><br>'
    . '<table border="1"><tr><td>A</td><td>B</td></tr></table><o:p>sampah</o:p></div>';
$wordRender = cbt_render_soal(cbt_siapkan_html_soal($word));
yes(str_contains($wordRender, '<strong>Tebal</strong>'), 'b dinormalisasi ke strong');
yes(str_contains($wordRender, '<em>Miring</em>'), 'i dinormalisasi ke em');
yes(str_contains($wordRender, '<table>'), 'tabel dipertahankan');
yes(! str_contains($wordRender, 'MsoNormal'), 'class Word dibuang');
yes(! str_contains($wordRender, 'font-family'), 'style Word dibuang');
yes(! str_contains($wordRender, '<o:p'), 'tag Office dibuang');

// Teks polos dari editor tetap aman dan notasi natural tetap berfungsi.
$natural = cbt_render_soal(cbt_siapkan_html_soal('<p>sqrt(x^2) dan (1)/(2)</p>'));
yes(str_contains($natural, 'mtk-akar'), 'notasi akar tetap dirender');
yes(str_contains($natural, 'mtk-frac'), 'notasi pecahan tetap dirender');

// Nilai kosong tidak boleh dianggap soal valid.
yes(cbt_html_teks_kosong('<p><br></p>'), 'HTML visual kosong dikenali');
yes(! cbt_html_teks_kosong('<p><img src="/uploads/soal-inline/x.png"></p>'), 'gambar dianggap isi');

echo "SEMUA TES RICH TEXT LULUS\n";
