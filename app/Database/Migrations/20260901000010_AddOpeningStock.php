<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOpeningStock extends Migration
{
    public function up()
    {
        $fields = ['opening_stock' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0]];
        $this->forge->addColumn('products', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('products', 'opening_stock');
    }
}
