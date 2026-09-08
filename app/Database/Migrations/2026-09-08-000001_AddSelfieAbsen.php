<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fitur Absen Selfie.
 *
 * Foto TIDAK disimpan sebagai BLOB di database (membuat DB membengkak
 * dan backup lambat). Yang disimpan hanya metadata + path file;
 * gambarnya sendiri ada di disk (writable/uploads/selfie) lewat
 * SelfieStore yang sudah mengompres + membuat thumbnail.
 */
class AddSelfieAbsen extends Migration
{
    public function up(): void
    {
        // flag per ujian: siswa wajib selfie sebelum boleh mulai
        $this->forge->addColumn('exams', [
            'absen_selfie' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'exam_id'     => ['type' => 'INT', 'unsigned' => true],
            'student_id'  => ['type' => 'INT', 'unsigned' => true],
            // path RELATIF terhadap writable/uploads/selfie (bukan blob!)
            'file_path'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'thumb_path'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],  // byte foto penuh
            'thumb_size'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],  // byte thumbnail
            'width'       => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'height'      => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'hash'        => ['type' => 'CHAR', 'constraint' => 64, 'null' => true], // sha256 isi file
            'ip'          => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // 1 siswa = 1 selfie per ujian (upload ulang menimpa, bukan menumpuk)
        $this->forge->addUniqueKey(['exam_id', 'student_id']);
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('selfies');
    }

    public function down(): void
    {
        $this->forge->dropTable('selfies', true);
        $this->forge->dropColumn('exams', 'absen_selfie');
    }
}
