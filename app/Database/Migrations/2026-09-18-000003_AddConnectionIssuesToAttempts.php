<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConnectionIssuesToAttempts extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('attempts', [
            'gangguan_koneksi' => [
                'type'       => 'INT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 0,
                'after'      => 'pelanggaran_terakhir_at',
            ],
            'gangguan_koneksi_terakhir_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'gangguan_koneksi',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('attempts', ['gangguan_koneksi', 'gangguan_koneksi_terakhir_at']);
    }
}
