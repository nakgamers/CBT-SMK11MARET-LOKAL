<?php

namespace App\Commands;

use App\Libraries\Sheet;
use App\Models\StudentModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ImportStudents extends BaseCommand
{
    protected $group = 'CBT';
    protected $name = 'cbt:import-students';
    protected $description = 'Import student spreadsheet columns: Username, Password, nama, kelas.';
    protected $usage = 'cbt:import-students <file>';

    public function run(array $params)
    {
        $path = $params[0] ?? '';
        if ($path === '' || ! is_file($path)) {
            CLI::error('File tidak ditemukan.');
            return EXIT_ERROR;
        }

        $rows = Sheet::rows($path, basename($path));
        if ($rows === []) {
            CLI::error('Tidak ada data siswa.');
            return EXIT_ERROR;
        }

        $seen = [];
        foreach ($rows as $index => $row) {
            [$username, $password, $nama, $kelas] = array_pad($row, 4, '');
            $line = $index + 2;
            if ($username === '' || $password === '' || $nama === '' || $kelas === '') {
                CLI::error("Baris {$line} tidak lengkap.");
                return EXIT_ERROR;
            }
            if (isset($seen[$username])) {
                CLI::error("Username duplikat pada baris {$line}.");
                return EXIT_ERROR;
            }
            if (strlen($password) < 4 || strlen($password) > 12) {
                CLI::error("Panjang password baris {$line} harus 4-12 karakter.");
                return EXIT_ERROR;
            }
            $seen[$username] = true;
        }

        $db = db_connect();
        $model = model(StudentModel::class);
        $inserted = 0;
        $updated = 0;
        $db->transStart();
        foreach ($rows as $row) {
            [$username, $password, $nama, $kelas] = array_pad($row, 4, '');
            $data = [
                'nama' => $nama,
                'kelas' => strtoupper($kelas),
                'jk' => 'L',
                'token' => strtoupper($password),
                'aktif' => 1,
            ];
            $existing = $model->where('nis', $username)->first();
            if ($existing) {
                $model->update($existing['id'], $data);
                $updated++;
            } else {
                $data['nis'] = $username;
                $model->insert($data);
                $inserted++;
            }
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            CLI::error('Transaksi database gagal.');
            return EXIT_ERROR;
        }

        CLI::write("imported={$inserted} updated={$updated} total=" . count($rows));
        return EXIT_SUCCESS;
    }
}
