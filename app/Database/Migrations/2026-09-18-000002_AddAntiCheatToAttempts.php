<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAntiCheatToAttempts extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('attempts', [
            'pelanggaran_cheat' => [
                'type'       => 'INT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 0,
                'after'      => 'ip',
            ],
            'pelanggaran_terakhir_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'pelanggaran_cheat',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('attempts', ['pelanggaran_cheat', 'pelanggaran_terakhir_at']);
    }
}
