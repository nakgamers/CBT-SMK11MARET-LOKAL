<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seluruh skema CBT dalam satu migration — tabelnya saling terkait,
 * memecahnya jadi 7 file tidak memberi keuntungan apa pun.
 */
class CreateCbtSchema extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------ admins
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'username'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'aktif'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('admins');

        // ------------------------------------------------ students
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nis'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'kelas'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'jk'         => ['type' => 'ENUM', 'constraint' => ['L', 'P'], 'default' => 'L'],
            // token kartu login: sengaja plaintext, harus bisa dicetak ke kartu siswa
            'token'      => ['type' => 'VARCHAR', 'constraint' => 12],
            'aktif'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nis');
        $this->forge->addKey('kelas');
        $this->forge->createTable('students');

        // ------------------------------------------------ banks (bank soal)
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 120],
            'mapel'      => ['type' => 'VARCHAR', 'constraint' => 80],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('banks');

        // ------------------------------------------------ questions
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'bank_id'    => ['type' => 'INT', 'unsigned' => true],
            'teks'       => ['type' => 'TEXT'],
            'gambar'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'opsi_a'     => ['type' => 'TEXT', 'null' => true],
            'opsi_b'     => ['type' => 'TEXT', 'null' => true],
            'opsi_c'     => ['type' => 'TEXT', 'null' => true],
            'opsi_d'     => ['type' => 'TEXT', 'null' => true],
            'opsi_e'     => ['type' => 'TEXT', 'null' => true],
            'kunci'      => ['type' => 'CHAR', 'constraint' => 1],
            'bobot'      => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('bank_id');
        $this->forge->addForeignKey('bank_id', 'banks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('questions');

        // ------------------------------------------------ exams
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama'            => ['type' => 'VARCHAR', 'constraint' => 150],
            'bank_id'         => ['type' => 'INT', 'unsigned' => true],
            // daftar kelas dipisah koma, '*' = semua kelas (dicek pakai FIND_IN_SET)
            'kelas'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => '*'],
            'jumlah_soal'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0], // 0 = semua soal di bank
            'durasi_menit'    => ['type' => 'INT', 'unsigned' => true, 'default' => 60],
            'mulai_at'        => ['type' => 'DATETIME'],
            'selesai_at'      => ['type' => 'DATETIME'],
            'acak_soal'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'acak_opsi'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'tampilkan_hasil' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'token'           => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'aktif'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('bank_id');
        $this->forge->addForeignKey('bank_id', 'banks', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('exams');

        // ------------------------------------------------ attempts
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'exam_id'      => ['type' => 'INT', 'unsigned' => true],
            'student_id'   => ['type' => 'INT', 'unsigned' => true],
            'urutan'       => ['type' => 'TEXT'], // JSON: urutan question_id milik peserta ini
            'started_at'   => ['type' => 'DATETIME'],
            'deadline_at'  => ['type' => 'DATETIME'],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'status'       => ['type' => 'ENUM', 'constraint' => ['berlangsung', 'selesai'], 'default' => 'berlangsung'],
            'skor'         => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'benar'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'salah'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'kosong'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'ip'           => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['exam_id', 'student_id']); // 1 siswa = 1 attempt per ujian
        $this->forge->addForeignKey('exam_id', 'exams', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('attempts');

        // ------------------------------------------------ answers
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'attempt_id'  => ['type' => 'INT', 'unsigned' => true],
            'question_id' => ['type' => 'INT', 'unsigned' => true],
            'jawaban'     => ['type' => 'CHAR', 'constraint' => 1, 'null' => true],
            'ragu'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'benar'       => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['attempt_id', 'question_id']);
        $this->forge->addForeignKey('attempt_id', 'attempts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('question_id', 'questions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('answers');
    }

    public function down(): void
    {
        $this->forge->dropTable('answers', true);
        $this->forge->dropTable('attempts', true);
        $this->forge->dropTable('exams', true);
        $this->forge->dropTable('questions', true);
        $this->forge->dropTable('banks', true);
        $this->forge->dropTable('students', true);
        $this->forge->dropTable('admins', true);
    }
}
