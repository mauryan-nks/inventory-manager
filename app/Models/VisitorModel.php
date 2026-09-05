<?php

namespace App\Models;

use CodeIgniter\Model;

class VisitorModel extends Model
{
    protected $table = 'visitors';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'visitor_type', 'name', 'photo_path', 'purpose', 'owner_id', 'status',
        'entry_at', 'approved_by', 'approved_at', 'rejected_reason', 'created_by',
        'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
