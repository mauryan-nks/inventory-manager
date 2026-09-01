<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'status',
        'last_login',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    public function findActiveByUsername(string $username): ?array
    {
        return $this->where('username', $username)
            ->where('status', 1)
            ->first();
    }
}
