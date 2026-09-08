<?php

namespace App\Commands;

use App\Models\AdminModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CbtAdmin extends BaseCommand
{
    protected $group       = 'CBT';
    protected $name        = 'cbt:admin';
    protected $description = 'Buat admin baru atau ubah passwordnya.';
    protected $usage       = 'cbt:admin <username> <password> [nama]';

    public function run(array $params)
    {
        $username = $params[0] ?? CLI::prompt('Username', 'admin');
        $password = $params[1] ?? CLI::prompt('Password', null, 'required');
        $nama     = $params[2] ?? 'Administrator';

        if (strlen($password) < 6) {
            CLI::error('Password minimal 6 karakter.');

            return EXIT_ERROR;
        }

        $model = model(AdminModel::class);
        $ada   = $model->where('username', $username)->first();

        $data = [
            'nama'          => $nama,
            'username'      => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'aktif'         => 1,
        ];

        if ($ada) {
            $model->update($ada['id'], $data);
            CLI::write("Password admin '{$username}' diperbarui.", 'green');

            return EXIT_SUCCESS;
        }

        if ($model->insert($data) === false) {
            CLI::error(implode(' ', $model->errors()));

            return EXIT_ERROR;
        }

        CLI::write("Admin '{$username}' dibuat.", 'green');

        return EXIT_SUCCESS;
    }
}
