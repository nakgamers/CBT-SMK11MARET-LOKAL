<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model
{
    protected $table         = 'admins';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['nama', 'username', 'password_hash', 'aktif'];

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', $username)->where('aktif', 1)->first();
    }
}
