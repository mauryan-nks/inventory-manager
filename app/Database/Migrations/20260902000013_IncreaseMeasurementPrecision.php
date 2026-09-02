<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class IncreaseMeasurementPrecision extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('products', [
            'opening_stock' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
            'minimum_stock' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
        ]);

        $this->forge->modifyColumn('inventory_transaction_items', [
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,6'],
            'entered_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('products', [
            'opening_stock' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'minimum_stock' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
        ]);
        $this->forge->modifyColumn('inventory_transaction_items', [
            'quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3'],
            'entered_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'null' => true],
        ]);
    }
}
