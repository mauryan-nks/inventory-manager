<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddPartyTaxLocationFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('order_parties', [
            'gstin' => ['type'=>'VARCHAR','constraint'=>15,'null'=>true,'after'=>'email'],
            'state' => ['type'=>'VARCHAR','constraint'=>100,'null'=>true,'after'=>'gstin'],
            'pincode' => ['type'=>'VARCHAR','constraint'=>10,'null'=>true,'after'=>'state'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('order_parties', ['gstin','state','pincode']);
    }
}
