<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryTransactionItemModel extends Model
{
    protected $table = 'inventory_transaction_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'transaction_id',
        'product_id',
        'variant_id',
        'variant_attributes_json',
        'quantity',
        'size_value',
        'size_unit',
        'size_inches',
        'entered_quantity',
        'entered_unit',
        'quantity_inches',
        'created_at',
    ];

    protected $useTimestamps = false;
}
