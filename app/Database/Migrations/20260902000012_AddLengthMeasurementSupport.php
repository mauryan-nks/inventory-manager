<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLengthMeasurementSupport extends Migration
{
    public function up()
    {
        $this->forge->addColumn('products', [
            'measurement_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'STANDARD',
                'after' => 'unit',
            ],
            'stock_unit' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'default' => 'UNIT',
                'after' => 'measurement_type',
            ],
        ]);

        $this->forge->addColumn('inventory_transaction_items', [
            'entered_quantity' => [
                'type' => 'DECIMAL',
                'constraint' => '18,3',
                'null' => true,
                'after' => 'quantity',
            ],
            'entered_unit' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
                'after' => 'entered_quantity',
            ],
            'quantity_inches' => [
                'type' => 'DECIMAL',
                'constraint' => '18,6',
                'null' => true,
                'after' => 'entered_unit',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('inventory_transaction_items', ['quantity_inches', 'entered_unit', 'entered_quantity']);
        $this->forge->dropColumn('products', ['stock_unit', 'measurement_type']);
    }
}
