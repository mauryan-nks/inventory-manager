<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeparateQuantityAndSize extends Migration
{
    public function up()
    {
        $this->forge->addColumn('inventory_transaction_items', [
            'size_value' => [
                'type' => 'DECIMAL',
                'constraint' => '18,6',
                'null' => true,
                'after' => 'quantity',
            ],
            'size_unit' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
                'after' => 'size_value',
            ],
            'size_inches' => [
                'type' => 'DECIMAL',
                'constraint' => '18,6',
                'null' => true,
                'after' => 'size_unit',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('inventory_transaction_items', ['size_inches', 'size_unit', 'size_value']);
    }
}
