<?php

namespace App\Commands;

use App\Libraries\ResultExport;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CbtExportClassTest extends BaseCommand
{
    protected $group = 'CBT';
    protected $name = 'cbt:test-export-class';
    protected $description = 'Test filter export hasil ujian per rombel.';

    public function run(array $params)
    {
        $hasil = [
            ['nis' => '1', 'nama' => 'A', 'kelas' => 'X AKL 1'],
            ['nis' => '2', 'nama' => 'B', 'kelas' => 'X AKL 2'],
            ['nis' => '3', 'nama' => 'C', 'kelas' => 'X AKL 1'],
        ];

        $kelas = ResultExport::classes($hasil);
        $filtered = ResultExport::filter($hasil, 'X AKL 1');
        $all = ResultExport::filter($hasil, '');
        $unknown = ResultExport::filter($hasil, 'XI RPL 1');

        $checks = [
            'daftar rombel unik dan terurut' => $kelas === ['X AKL 1', 'X AKL 2'],
            'filter hanya rombel terpilih' => count($filtered) === 2
                && array_column($filtered, 'nis') === ['1', '3'],
            'kelas kosong mengekspor semua' => count($all) === 3,
            'kelas tak dikenal menghasilkan kosong' => $unknown === [],
            'slug nama file aman' => ResultExport::slug('X AKL 1') === 'x-akl-1',
        ];

        $failed = 0;
        foreach ($checks as $label => $ok) {
            CLI::write(($ok ? 'PASS' : 'FAIL') . '  ' . $label);
            $failed += $ok ? 0 : 1;
        }

        return $failed === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
