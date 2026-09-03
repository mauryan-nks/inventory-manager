<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'category_id',
        'code',
        'name',
        'unit',
        'measurement_type',
        'stock_unit',
        'minimum_stock',
        'opening_stock',
        'description',
        'variant_schema_json',
        'status',
        'created_at',
        'updated_at',
    ];
}
