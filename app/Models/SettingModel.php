<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table = 'app_settings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['setting_key', 'setting_value', 'updated_by', 'updated_at'];
    protected $useTimestamps = false;

    public function value(string $key, ?string $default = null): ?string
    {
        $row = $this->where('setting_key', $key)->first();
        return $row['setting_value'] ?? $default;
    }
}
