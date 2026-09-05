<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddOperatorMachineToFactoryProductions extends Migration
{
    public function up()
    {
        $this->forge->addColumn('factory_productions', [
            'operator_name' => ['type'=>'VARCHAR','constraint'=>150,'null'=>true,'after'=>'item_name'],
            'machine_name' => ['type'=>'VARCHAR','constraint'=>150,'null'=>true,'after'=>'operator_name'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('factory_productions', ['operator_name','machine_name']);
    }
}
