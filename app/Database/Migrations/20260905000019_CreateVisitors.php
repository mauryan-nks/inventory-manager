<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVisitors extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'visitor_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'GENERAL'],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'photo_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'purpose' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'owner_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'CHECKED_IN'],
            'entry_at' => ['type' => 'DATETIME'],
            'approved_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'rejected_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('owner_id');
        $this->forge->addKey('status');
        $this->forge->addKey('entry_at');
        $this->forge->createTable('visitors');
    }

    public function down()
    {
        $this->forge->dropTable('visitors', true);
    }
}
