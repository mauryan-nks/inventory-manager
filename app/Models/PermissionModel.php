<?php

namespace App\Models;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table = 'user_permissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_id',
        'permission',
        'created_at',
    ];

    protected $useTimestamps = false;

    public function getForUser(int $userId): array
    {
        $rows = $this->select('permission')
            ->where('user_id', $userId)
            ->findAll();

        return array_values(array_unique(array_column($rows, 'permission')));
    }

    public function hasPermission(int $userId, string $permission): bool
    {
        return $this->where([
            'user_id' => $userId,
            'permission' => $permission,
        ])->countAllResults() > 0;
    }

    public function replaceForUser(int $userId, array $permissions): void
    {
        $this->where('user_id', $userId)->delete();

        $now = date('Y-m-d H:i:s');

        foreach (array_unique($permissions) as $permission) {
            if ($permission === '') {
                continue;
            }

            $this->insert([
                'user_id' => $userId,
                'permission' => $permission,
                'created_at' => $now,
            ]);
        }
    }
}
