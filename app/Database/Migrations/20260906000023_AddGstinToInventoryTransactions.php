<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddGstinToInventoryTransactions extends Migration
{
 public function up(){ $this->forge->addColumn('inventory_transactions',['party_gstin'=>['type'=>'VARCHAR','constraint'=>15,'null'=>true,'after'=>'party_name']]); }
 public function down(){ $this->forge->dropColumn('inventory_transactions','party_gstin'); }
}
