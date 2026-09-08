<?php

namespace App\Commands;

use App\Models\BankModel;
use App\Models\ExamModel;
use App\Models\QuestionModel;
use App\Models\StudentModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CbtSeed extends BaseCommand
{
    protected $group       = 'CBT';
    protected $name        = 'cbt:seed';
    protected $description = 'Isi data contoh: 1 bank + 10 soal, 6 siswa, 3 ujian (belum/berlangsung/lewat).';

    public function run(array $params)
    {
        helper('cbt');

        // ------------------------------------------------------------ bank + soal
        $bankModel = model(BankModel::class);
        $bank      = $bankModel->where('nama', 'Contoh UAS Ganjil')->first();
        $bankId    = $bank ? (int) $bank['id'] : (int) $bankModel->insert([
            'nama'       => 'Contoh UAS Ganjil',
            'mapel'      => 'Matematika',
            'keterangan' => 'Data contoh dari cbt:seed',
        ], true);

        $soal = [
            // teks, A, B, C, D, E, kunci
            ['Hasil dari 12 x 8 adalah ...', '86', '96', '104', '112', '120', 'B'],
            ['Nilai dari 2^5 adalah ...', '10', '25', '32', '64', '128', 'C'],
            ['Bilangan prima terkecil adalah ...', '0', '1', '2', '3', '5', 'C'],
            ['Jika 3x = 27 maka x = ...', '9', '6', '3', '12', '18', 'A'],
            ['Luas persegi dengan sisi 7 cm adalah ...', '28 cm²', '42 cm²', '49 cm²', '56 cm²', '64 cm²', 'C'],
            ['KPK dari 4 dan 6 adalah ...', '10', '12', '18', '24', '36', 'B'],
            ['FPB dari 18 dan 24 adalah ...', '2', '3', '6', '9', '12', 'C'],
            ['Rata-rata dari 4, 6, 8, 10 adalah ...', '6', '7', '8', '9', '10', 'B'],
            ['15% dari 200 adalah ...', '20', '25', '30', '35', '40', 'C'],
            ['Keliling lingkaran berdiameter 14 cm (π=22/7) adalah ...', '22 cm', '44 cm', '88 cm', '154 cm', '196 cm', 'B'],
        ];

        $qModel  = model(QuestionModel::class);
        $adaSoal = $qModel->where('bank_id', $bankId)->countAllResults();
        if ($adaSoal === 0) {
            foreach ($soal as $s) {
                $qModel->insert([
                    'bank_id' => $bankId,
                    'teks'    => $s[0],
                    'opsi_a'  => $s[1],
                    'opsi_b'  => $s[2],
                    'opsi_c'  => $s[3],
                    'opsi_d'  => $s[4],
                    'opsi_e'  => $s[5],
                    'kunci'   => $s[6],
                    'bobot'   => 1,
                ]);
            }
            CLI::write('10 soal contoh ditambahkan.', 'green');
        } else {
            CLI::write("Bank sudah punya {$adaSoal} soal, dilewati.", 'yellow');
        }

        // ------------------------------------------------------------------ siswa
        $sModel = model(StudentModel::class);
        $siswa  = [
            ['2024001', 'Ahmad Fauzi', 'XII RPL 1', 'L'],
            ['2024002', 'Siti Aminah', 'XII RPL 1', 'P'],
            ['2024003', 'Budi Santoso', 'XII RPL 1', 'L'],
            ['2024004', 'Dewi Lestari', 'XII RPL 2', 'P'],
            ['2024005', 'Eko Prasetyo', 'XII RPL 2', 'L'],
            ['2024006', 'Fitri Handayani', 'XII TKJ 1', 'P'],
        ];

        $baru = 0;
        foreach ($siswa as $s) {
            if ($sModel->where('nis', $s[0])->first()) {
                continue;
            }
            $sModel->insert([
                'nis'   => $s[0],
                'nama'  => $s[1],
                'kelas' => $s[2],
                'jk'    => $s[3],
                'token' => StudentModel::generateToken(),
                'aktif' => 1,
            ]);
            $baru++;
        }
        CLI::write("{$baru} siswa contoh ditambahkan.", 'green');

        // ------------------------------------------------------------------ ujian
        $eModel = model(ExamModel::class);
        $jadwal = [
            ['Contoh: Sedang Berlangsung', '-1 hour', '+7 day', 60, 0],
            ['Contoh: Belum Dimulai', '+3 day', '+4 day', 45, 5],
            ['Contoh: Sudah Berakhir', '-7 day', '-6 day', 30, 5],
        ];

        foreach ($jadwal as $j) {
            if ($eModel->where('nama', $j[0])->first()) {
                continue;
            }
            $eModel->insert([
                'nama'            => $j[0],
                'bank_id'         => $bankId,
                'kelas'           => '*',
                'jumlah_soal'     => $j[4],
                'durasi_menit'    => $j[3],
                'mulai_at'        => date('Y-m-d H:i:s', strtotime($j[1])),
                'selesai_at'      => date('Y-m-d H:i:s', strtotime($j[2])),
                'acak_soal'       => 1,
                'acak_opsi'       => 0,
                'tampilkan_hasil' => 1,
                'aktif'           => 1,
            ]);
        }
        CLI::write('3 ujian contoh (belum / berlangsung / lewat) siap.', 'green');

        CLI::newLine();
        CLI::write('Kartu login siswa contoh:', 'yellow');
        foreach ($sModel->orderBy('nis')->findAll() as $s) {
            CLI::write(sprintf('  %-10s %-20s %-12s token: %s', $s['nis'], $s['nama'], $s['kelas'], $s['token']));
        }

        return EXIT_SUCCESS;
    }
}
