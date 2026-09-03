<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductVariantModel extends Model
{
    protected $table = 'product_variants';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'product_id', 'variant_name', 'attributes_json', 'size_value', 'size_unit', 'size_inches',
        'opening_quantity', 'minimum_quantity', 'status', 'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
