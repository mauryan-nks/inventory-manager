<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryTransactionModel extends Model
{
    protected $table = 'inventory_transactions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'transaction_no',
        'type',
        'reference_no',
        'party_name',
        'vehicle_no',
        'remarks',
        'created_by',
        'status',
        'created_at',
        'updated_at',
    ];
}
