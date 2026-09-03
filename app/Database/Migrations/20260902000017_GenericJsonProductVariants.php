<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class GenericJsonProductVariants extends Migration
{
    public function up()
    {
        $db = db_connect();

        // Keep legacy size columns for backward compatibility, but make JSON the canonical
        // variant description. TEXT is used instead of a vendor-specific JSON column.
        if (!$db->fieldExists('attributes_json', 'product_variants')) {
            $this->forge->addColumn('product_variants', [
                'attributes_json' => ['type' => 'TEXT', 'null' => true, 'after' => 'variant_name'],
            ]);
        }
        if (!$db->fieldExists('variant_attributes_json', 'inventory_transaction_items')) {
            $this->forge->addColumn('inventory_transaction_items', [
                'variant_attributes_json' => ['type' => 'TEXT', 'null' => true, 'after' => 'variant_id'],
            ]);
        }
        if (!$db->fieldExists('variant_schema_json', 'products')) {
            $this->forge->addColumn('products', [
                'variant_schema_json' => ['type' => 'TEXT', 'null' => true, 'after' => 'description'],
            ]);
        }

        // Convert the old size-only variants to the new generic JSON representation.
        $rows = $db->table('product_variants')->select('id,variant_name,size_value,size_unit,size_inches,attributes_json')->get()->getResultArray();
        foreach ($rows as $row) {
            if (trim((string)($row['attributes_json'] ?? '')) !== '') continue;
            $attributes = [];
            if ($row['size_value'] !== null && $row['size_unit']) {
                $attributes['size'] = ['value' => (float)$row['size_value'], 'unit' => strtoupper((string)$row['size_unit'])];
            } elseif (($row['variant_name'] ?? '') !== '') {
                $attributes['variant'] = (string)$row['variant_name'];
            }
            $db->table('product_variants')->where('id', $row['id'])->update([
                'attributes_json' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        // Snapshot generic attributes on existing transaction lines.
        $db->query("UPDATE inventory_transaction_items i
            INNER JOIN product_variants v ON v.id=i.variant_id
            SET i.variant_attributes_json=v.attributes_json
            WHERE i.variant_attributes_json IS NULL");
    }

    public function down()
    {
        $this->forge->dropColumn('inventory_transaction_items', 'variant_attributes_json');
        $this->forge->dropColumn('product_variants', 'attributes_json');
        $this->forge->dropColumn('products', 'variant_schema_json');
    }
}
