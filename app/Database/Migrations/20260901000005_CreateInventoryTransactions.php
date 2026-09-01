<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryTransactions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'transaction_no' => ['type' => 'VARCHAR', 'constraint' => 50],
            'type' => ['type' => 'ENUM', 'constraint' => ['IN', 'OUT']],
            'reference_no' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'party_name' => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'vehicle_no' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'remarks' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'CONFIRMED'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('transaction_no');
        $this->forge->addKey('type');
        $this->forge->addKey('created_by');
        $this->forge->createTable('inventory_transactions');
    }

    public function down()
    {
        $this->forge->dropTable('inventory_transactions', true);
    }
}
