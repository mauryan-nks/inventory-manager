<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductVariants extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'product_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'variant_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'size_value' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'null' => true],
            'size_unit' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'size_inches' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'null' => true],
            'opening_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
            'minimum_quantity' => ['type' => 'DECIMAL', 'constraint' => '18,6', 'default' => 0],
            'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('product_id');
        $this->forge->addKey(['product_id', 'status']);
        $this->forge->createTable('product_variants');

        // Every existing product receives one default variant so historical transactions
        // can be safely attributed to a variant without changing the product totals.
        $db = db_connect();
        $products = $db->query('SELECT id, name, measurement_type, opening_stock, minimum_stock FROM products')->getResultArray();
        foreach ($products as $product) {
            $db->table('product_variants')->insert([
                'product_id' => (int)$product['id'],
                'variant_name' => strtoupper((string)$product['measurement_type']) === 'LENGTH' ? 'Unspecified size' : 'Default',
                'size_value' => null,
                'size_unit' => null,
                'size_inches' => null,
                'opening_quantity' => (float)($product['opening_stock'] ?? 0),
                'minimum_quantity' => (float)($product['minimum_stock'] ?? 0),
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Attribute every legacy transaction item to its product's default variant.
        $db->query('UPDATE inventory_transaction_items i INNER JOIN product_variants v ON v.product_id = i.product_id AND v.variant_name IN (\'Default\', \'Unspecified size\') SET i.variant_id = v.id WHERE i.variant_id IS NULL');
    }

    public function down()
    {
        // Keep the variant_id column; it may have existed before this migration in some installs.
        $this->forge->dropTable('product_variants', true);
    }
}
