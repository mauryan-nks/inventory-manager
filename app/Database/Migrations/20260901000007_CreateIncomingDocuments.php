<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateIncomingDocuments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'transaction_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'uploaded_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'file_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'incoming'],
            'ocr_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'ocr_data' => ['type' => 'LONGTEXT', 'null' => true],
            'verified' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'verified_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'verified_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('transaction_id');
        $this->forge->addKey('uploaded_by');
        $this->forge->createTable('incoming_documents');
    }

    public function down()
    {
        $this->forge->dropTable('incoming_documents', true);
    }
}
