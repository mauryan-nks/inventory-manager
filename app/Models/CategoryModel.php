<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table = 'categories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'name',
        'description',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;
}
