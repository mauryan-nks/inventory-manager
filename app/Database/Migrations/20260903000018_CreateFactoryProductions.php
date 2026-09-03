<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateFactoryProductions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
            'production_date'=>['type'=>'DATE'],
            'product_id'=>['type'=>'BIGINT','unsigned'=>true],
            'category_id'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'item_name'=>['type'=>'VARCHAR','constraint'=>200],
            'quantity'=>['type'=>'BIGINT','unsigned'=>true],
            'transaction_id'=>['type'=>'BIGINT','unsigned'=>true,'null'=>true],
            'created_by'=>['type'=>'BIGINT','unsigned'=>true],
            'created_at'=>['type'=>'DATETIME','null'=>true],
            'updated_at'=>['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id',true);
        $this->forge->addKey('production_date');
        $this->forge->addKey('product_id');
        $this->forge->addKey('category_id');
        $this->forge->addKey('transaction_id');
        $this->forge->createTable('factory_productions');
    }
    public function down(){ $this->forge->dropTable('factory_productions',true); }
}
