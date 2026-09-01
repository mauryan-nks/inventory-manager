<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDefaultAdmin extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        $exists = $this->db->table('users')->where('username', 'admin')->get()->getRowArray();

        if (!$exists) {
            $this->db->table('users')->insert([
                'name' => 'Administrator',
                'username' => 'admin',
                'email' => null,
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        $this->db->table('users')->where('username', 'admin')->delete();
    }
}
