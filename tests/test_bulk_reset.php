<?php

declare(strict_types=1);

// Self-check untuk bulk reset attempt. Jalankan:
// php tests/test_bulk_reset.php

function same($expected, $actual, string $name): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "GAGAL {$name}\nEXPECTED: " . json_encode($expected) . "\nACTUAL  : " . json_encode($actual) . "\n");
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

// 1. Normalisasi student_id (logika di Exams::resetAttempt)
function normalisasiIds($input): array
{
    $ids = is_array($input) ? array_map('intval', $input) : [(int) $input];
    return array_values(array_filter($ids));
}

same([5], normalisasiIds('5'), 'single string ID dinormalisasi ke integer array');
same([12], normalisasiIds(12), 'single int ID dinormalisasi ke integer array');
same([1, 2, 3], normalisasiIds(['1', '2', '3']), 'array ID string dinormalisasi ke integer array');
same([1, 2], normalisasiIds(['1', '0', '2', '']), 'array membuang ID 0 dan string kosong');
same([], normalisasiIds([]), 'array kosong tetap kosong');
same([], normalisasiIds('0'), 'ID string 0 dianggap kosong');
same([], normalisasiIds(null), 'null dianggap kosong');

// 2. Verifikasi konten controller Exams.php
$baseDir = is_dir(__DIR__ . '/../app/Controllers') ? __DIR__ . '/..' : (is_dir('/app/app/Controllers') ? '/app' : getcwd());
$controllerPath = $baseDir . '/app/Controllers/Admin/Exams.php';
$controllerCode = file_get_contents($controllerPath);
yes(strpos($controllerCode, 'whereIn(\'student_id\', $ids)') !== false, 'controller memakai whereIn untuk student_id');
yes(strpos($controllerCode, 'is_array($ids)') !== false, 'controller mendukung array dan single student_id');
yes(strpos($controllerCode, 'Pilih dulu siswa yang mau direset.') !== false, 'controller validasi empty selection');
yes(strpos($controllerCode, 'attempt direset, siswa dapat mengerjakan ulang.') !== false, 'controller flash message jumlah reset');

// 3. Verifikasi view hasil.php
$viewPath = $baseDir . '/app/Views/admin/hasil.php';
$viewCode = file_get_contents($viewPath);
yes(strpos($viewCode, 'id="cekSemua"') !== false, 'view memuat checkbox #cekSemua di header');
yes(strpos($viewCode, 'class="cekSiswa"') !== false, 'view memuat checkbox .cekSiswa di baris');
yes(strpos($viewCode, 'id="btnResetMassal"') !== false, 'view memuat tombol #btnResetMassal');
yes(strpos($viewCode, 'id="jmlCeklis"') !== false, 'view memuat counter #jmlCeklis');
yes(strpos($viewCode, 'student_id[]') !== false, 'view JS membangun form dinamis dengan input student_id[]');
yes(strpos($viewCode, 'confirm(') !== false, 'view JS melakukan konfirmasi sebelum submit');
yes(strpos($viewCode, 'id="sortNilai"') !== false, 'view memuat header sortable #sortNilai');
yes(strpos($viewCode, 'data-skor=') !== false, 'view baris tr memuat atribut data-skor');
yes(strpos($viewCode, 'sort-ind') !== false, 'view memuat elemen indikator sort .sort-ind');

// 4. Verifikasi CSS
$cssPath = $baseDir . '/public/assets/css/app.css';
$cssCode = file_get_contents($cssPath);
yes(strpos($cssCode, 'th.sortable') !== false, 'css memuat style th.sortable');
yes(strpos($cssCode, '.sort-ind') !== false, 'css memuat style .sort-ind');

echo "SEMUA TES BULK RESET & SORT LULUS\n";
