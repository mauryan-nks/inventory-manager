<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrdersModule extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'party_type' => ['type'=>'VARCHAR','constraint'=>20],
            'name' => ['type'=>'VARCHAR','constraint'=>200],
            'code' => ['type'=>'VARCHAR','constraint'=>80,'null'=>true],
            'contact_person' => ['type'=>'VARCHAR','constraint'=>150,'null'=>true],
            'phone' => ['type'=>'VARCHAR','constraint'=>50,'null'=>true],
            'email' => ['type'=>'VARCHAR','constraint'=>190,'null'=>true],
            'address' => ['type'=>'TEXT','null'=>true],
            'status' => ['type'=>'TINYINT','constraint'=>1,'default'=>1],
            'created_by' => ['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('party_type');
        $this->forge->addKey('status');
        $this->forge->createTable('order_parties');

        $this->forge->addField([
            'id' => ['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'order_no' => ['type'=>'VARCHAR','constraint'=>60],
            'party_id' => ['type'=>'BIGINT','unsigned'=>true],
            'order_date' => ['type'=>'DATE'],
            'delivery_start_date' => ['type'=>'DATE','null'=>true],
            'delivery_end_date' => ['type'=>'DATE','null'=>true],
            'status' => ['type'=>'VARCHAR','constraint'=>30,'default'=>'OPEN'],
            'notes' => ['type'=>'TEXT','null'=>true],
            'created_by' => ['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('order_no');
        $this->forge->addKey('party_id');
        $this->forge->addKey('order_date');
        $this->forge->addKey('status');
        $this->forge->createTable('orders');

        $this->forge->addField([
            'id' => ['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'order_id' => ['type'=>'BIGINT','unsigned'=>true],
            'product_id' => ['type'=>'BIGINT','unsigned'=>true],
            'variant_id' => ['type'=>'BIGINT','unsigned'=>true],
            'item_name' => ['type'=>'VARCHAR','constraint'=>200],
            'variant_name' => ['type'=>'VARCHAR','constraint'=>250,'null'=>true],
            'quantity' => ['type'=>'DECIMAL','constraint'=>'18,3'],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('product_id');
        $this->forge->addKey('variant_id');
        $this->forge->createTable('order_items');

        $this->forge->addField([
            'id' => ['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'order_id' => ['type'=>'BIGINT','unsigned'=>true],
            'order_item_id' => ['type'=>'BIGINT','unsigned'=>true],
            'delivery_date' => ['type'=>'DATE'],
            'quantity' => ['type'=>'DECIMAL','constraint'=>'18,3'],
            'reference_no' => ['type'=>'VARCHAR','constraint'=>150,'null'=>true],
            'remarks' => ['type'=>'TEXT','null'=>true],
            'created_by' => ['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('order_item_id');
        $this->forge->createTable('order_deliveries');

        $this->forge->addField([
            'id' => ['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'order_id' => ['type'=>'BIGINT','unsigned'=>true],
            'stage' => ['type'=>'VARCHAR','constraint'=>40],
            'file_name' => ['type'=>'VARCHAR','constraint'=>255],
            'file_path' => ['type'=>'VARCHAR','constraint'=>500],
            'mime_type' => ['type'=>'VARCHAR','constraint'=>120,'null'=>true],
            'file_size' => ['type'=>'BIGINT','unsigned'=>true,'default'=>0],
            'created_by' => ['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('stage');
        $this->forge->createTable('order_documents');
    }

    public function down()
    {
        $this->forge->dropTable('order_documents', true);
        $this->forge->dropTable('order_deliveries', true);
        $this->forge->dropTable('order_items', true);
        $this->forge->dropTable('orders', true);
        $this->forge->dropTable('order_parties', true);
    }
}
