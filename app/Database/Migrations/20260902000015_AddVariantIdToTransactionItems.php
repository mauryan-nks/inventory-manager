<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVariantIdToTransactionItems extends Migration
{
    public function up()
    {
        $this->forge->addColumn('inventory_transaction_items', [
            'variant_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
                'after' => 'product_id',
            ],
        ]);
        $this->forge->addKey('variant_id');
    }

    public function down()
    {
        $this->forge->dropColumn('inventory_transaction_items', 'variant_id');
    }
}
