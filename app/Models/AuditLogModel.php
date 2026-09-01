<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_id', 'action', 'module', 'record_id', 'description',
        'old_data', 'new_data', 'ip_address', 'created_at',
    ];
    protected $useTimestamps = false;
}
