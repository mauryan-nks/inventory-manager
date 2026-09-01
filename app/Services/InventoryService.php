<?php

namespace App\Services;

use App\Models\InventoryTransactionItemModel;
use App\Models\InventoryTransactionModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class InventoryService
{
    protected BaseConnection $db;
    protected ProductModel $products;
    protected ProductVariantModel $variants;
    protected InventoryTransactionModel $transactions;
    protected InventoryTransactionItemModel $items;

    public function __construct()
    {
        $this->db = db_connect();
        $this->products = new ProductModel();
        $this->variants = new ProductVariantModel();
        $this->transactions = new InventoryTransactionModel();
        $this->items = new InventoryTransactionItemModel();
    }

    public function variantStock(int $variantId): float
    {
        $row = $this->db->query("SELECT
                COALESCE(v.opening_quantity, 0) + COALESCE(SUM(
                    CASE
                        WHEN t.status='CONFIRMED' AND t.type='IN' THEN i.quantity
                        WHEN t.status='CONFIRMED' AND t.type='OUT' THEN -i.quantity
                        ELSE 0
                    END
                ), 0) AS stock
            FROM product_variants v
            LEFT JOIN inventory_transaction_items i ON i.variant_id = v.id
            LEFT JOIN inventory_transactions t ON t.id = i.transaction_id
            WHERE v.id = ?
            GROUP BY v.id, v.opening_quantity", [$variantId])->getRowArray();
        return max(0, (float)($row['stock'] ?? 0));
    }

    public function stock(int $productId): float
    {
        $row = $this->db->query("SELECT COALESCE(SUM(v.opening_quantity),0) + COALESCE(SUM(m.movements),0) AS stock
            FROM product_variants v
            LEFT JOIN (
                SELECT i.variant_id,
                    SUM(CASE WHEN t.status='CONFIRMED' AND t.type='IN' THEN i.quantity
                             WHEN t.status='CONFIRMED' AND t.type='OUT' THEN -i.quantity ELSE 0 END) AS movements
                FROM inventory_transaction_items i
                INNER JOIN inventory_transactions t ON t.id=i.transaction_id
                GROUP BY i.variant_id
            ) m ON m.variant_id=v.id
            WHERE v.product_id=?
            GROUP BY v.product_id", [$productId])->getRowArray();
        return max(0, (float)($row['stock'] ?? 0));
    }

    public function stocks(): array
    {
        $sql = "SELECT p.id, p.code, p.name, p.unit, p.measurement_type, p.stock_unit,
                p.minimum_stock, p.opening_stock,
                COALESCE(vb.current_stock,0) AS current_stock
            FROM products p
            LEFT JOIN (
                SELECT v.product_id,
                    SUM(COALESCE(v.opening_quantity,0) + COALESCE(m.movements,0)) AS current_stock
                FROM product_variants v
                LEFT JOIN (
                    SELECT i.variant_id,
                        SUM(CASE WHEN t.status='CONFIRMED' AND t.type='IN' THEN i.quantity
                                 WHEN t.status='CONFIRMED' AND t.type='OUT' THEN -i.quantity ELSE 0 END) AS movements
                    FROM inventory_transaction_items i
                    INNER JOIN inventory_transactions t ON t.id=i.transaction_id
                    GROUP BY i.variant_id
                ) m ON m.variant_id=v.id
                GROUP BY v.product_id
            ) vb ON vb.product_id=p.id
            ORDER BY p.name ASC";
        $rows = $this->db->query($sql)->getResultArray();
        foreach ($rows as &$row) {
            $row['variants'] = $this->variantsForProduct((int)$row['id']);
        }
        unset($row);
        return $rows;
    }

    public function variantsForProduct(int $productId): array
    {
        $rows = $this->db->query("SELECT v.*, COALESCE(v.opening_quantity,0) + COALESCE(SUM(CASE
                WHEN t.status='CONFIRMED' AND t.type='IN' THEN i.quantity
                WHEN t.status='CONFIRMED' AND t.type='OUT' THEN -i.quantity
                ELSE 0 END),0) AS current_stock
            FROM product_variants v
            LEFT JOIN inventory_transaction_items i ON i.variant_id=v.id
            LEFT JOIN inventory_transactions t ON t.id=i.transaction_id
            WHERE v.product_id=?
            GROUP BY v.id,v.product_id,v.variant_name,v.size_value,v.size_unit,v.size_inches,v.opening_quantity,v.minimum_quantity,v.status,v.created_at,v.updated_at
            ORDER BY (v.size_inches IS NULL), v.size_inches, v.variant_name", [$productId])->getResultArray();
        return $rows;
    }

    public function variantMap(): array
    {
        $map = [];
        foreach ($this->variants->where('status', 1)->orderBy('variant_name')->findAll() as $variant) {
            $map[(int)$variant['id']] = $variant;
        }
        return $map;
    }

    public function createTransaction(string $type, array $items, int $userId, array $header = []): int
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, ['IN', 'OUT'], true)) throw new RuntimeException('Invalid transaction type.');
        if (!$items) throw new RuntimeException('At least one product is required.');

        $normalised = [];
        $this->db->transBegin();
        try {
            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $variantId = (int)($item['variant_id'] ?? 0);
                $quantity = (float)($item['quantity'] ?? 0);
                if ($productId <= 0 || $variantId <= 0 || $quantity <= 0) throw new RuntimeException('Every line needs a product, variant and positive quantity.');

                $product = $this->db->query('SELECT * FROM products WHERE id=? FOR UPDATE', [$productId])->getRowArray();
                $variant = $this->db->query('SELECT * FROM product_variants WHERE id=? AND product_id=? AND status=1 FOR UPDATE', [$variantId,$productId])->getRowArray();
                if (!$product || (int)$product['status'] !== 1 || !$variant) throw new RuntimeException('One of the selected product variants is invalid or inactive.');

                $normalised[] = [
                    'product_id'=>$productId,
                    'variant_id'=>$variantId,
                    'quantity'=>$quantity,
                    'entered_quantity'=>$quantity,
                    'entered_unit'=>(string)($product['unit'] ?? 'UNIT'),
                    'size_value'=>$variant['size_value'],
                    'size_unit'=>$variant['size_unit'],
                    'size_inches'=>$variant['size_inches'],
                ];
            }

            if ($type === 'OUT') {
                $requested = [];
                foreach ($normalised as $item) $requested[$item['variant_id']] = ($requested[$item['variant_id']] ?? 0) + $item['quantity'];
                foreach ($requested as $variantId => $quantity) {
                    $available = $this->variantStock((int)$variantId);
                    if ($quantity > $available + 0.000001) {
                        $v = $this->db->query('SELECT v.variant_name,p.name,p.unit FROM product_variants v INNER JOIN products p ON p.id=v.product_id WHERE v.id=? FOR UPDATE', [$variantId])->getRowArray();
                        $name = $v['name'] ?? ('Variant #' . $variantId);
                        $label = $v['variant_name'] ?? '';
                        throw new RuntimeException("Insufficient stock for {$name} ({$label}). Available: {$available} {$v['unit']} requested: {$quantity} {$v['unit']}.");
                    }
                }
            }

            $transactionNo = $this->generateTransactionNo($type);
            $transactionId = $this->transactions->insert([
                'transaction_no'=>$transactionNo,'type'=>$type,
                'reference_no'=>$header['reference_no'] ?? null,'party_name'=>$header['party_name'] ?? null,
                'vehicle_no'=>$header['vehicle_no'] ?? null,'remarks'=>$header['remarks'] ?? null,
                'created_by'=>$userId,'status'=>'CONFIRMED','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
            ], true);
            if (!$transactionId) throw new RuntimeException('Could not create inventory transaction.');

            foreach ($normalised as $item) {
                $this->items->insert([
                    'transaction_id'=>$transactionId,'product_id'=>$item['product_id'],'variant_id'=>$item['variant_id'],
                    'quantity'=>$item['quantity'],'entered_quantity'=>$item['entered_quantity'],'entered_unit'=>$item['entered_unit'],
                    'quantity_inches'=>null,'size_value'=>$item['size_value'],'size_unit'=>$item['size_unit'],'size_inches'=>$item['size_inches'],
                    'created_at'=>date('Y-m-d H:i:s'),
                ]);
            }

            if (!$this->db->transStatus()) throw new RuntimeException('Database transaction failed.');
            $this->db->transCommit();
            (new AuditService())->record('CREATE_TRANSACTION','inventory',(int)$transactionId,'Inventory '.$type.' transaction created.',null,['transaction_no'=>$transactionNo,'items'=>$normalised]);
            return (int)$transactionId;
        } catch (\Throwable $e) {
            $this->db->transRollback(); throw $e;
        }
    }

    protected function generateTransactionNo(string $type): string
    {
        return ($type==='IN'?'IN':'OUT').'-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
    }
}
