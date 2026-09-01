<?php

namespace App\Controllers;

use App\Models\InventoryTransactionModel;
use App\Models\ProductModel;
use App\Services\InventoryService;
use RuntimeException;
use App\Services\AuthService;

class Inventory extends BaseController
{
    protected InventoryService $inventory;
    protected ProductModel $products;

    public function __construct()
    {
        $this->inventory = new InventoryService();
        $this->products = new ProductModel();
    }

    public function index()
    {
        $q = trim((string) $this->request->getGet('q'));
        $stocks = $this->inventory->stocks();
        if ($q !== '') {
            $stocks = array_values(array_filter($stocks, fn($s) => stripos((string)$s['name'], $q) !== false || stripos((string)$s['code'], $q) !== false));
        }
        return view('inventory/index', [
            'title' => 'Current Stock',
            'authNav' => new AuthService(),
            'stocks' => $stocks,
            'q' => $q,
            'lowCount' => count(array_filter($stocks, fn($s) => (float)$s['current_stock'] <= (float)$s['minimum_stock'])),
        ]);
    }

    public function in()
    {
        return view('inventory/form', [
            'title' => 'Inventory IN',
            'type' => 'IN',
            'products' => $this->products->where('status', 1)->orderBy('name')->findAll(),
            'stockMap' => $this->stockMap(),
            'productMap' => $this->productMap(),
            'variantMap' => $this->inventory->variantMap(),
            'variantStockMap' => $this->variantStockMap(),
        ]);
    }

    public function out()
    {
        return view('inventory/form', [
            'title' => 'Inventory OUT',
            'type' => 'OUT',
            'products' => $this->products->where('status', 1)->orderBy('name')->findAll(),
            'stockMap' => $this->stockMap(),
            'productMap' => $this->productMap(),
            'variantMap' => $this->inventory->variantMap(),
            'variantStockMap' => $this->variantStockMap(),
        ]);
    }

    public function store()
    {
        $type = strtoupper(trim((string) $this->request->getPost('type')));
        $auth = new AuthService();

        if (!in_array($type, ['IN', 'OUT'], true)) {
            return redirect()->to('/inventory')->with('error', 'Invalid transaction type.');
        }

        if (!$auth->can('inventory.' . strtolower($type))) {
            return redirect()->to('/dashboard')->with('error', 'You do not have permission to create this transaction.');
        }

        $productIds = $this->request->getPost('product_id') ?? [];
        $quantities = $this->request->getPost('quantity') ?? [];
        $variantIds = $this->request->getPost('variant_id') ?? [];

        $items = [];
        if (is_array($productIds) && is_array($quantities)) {
            foreach ($productIds as $index => $productId) {
                $items[] = [
                    'product_id' => (int)$productId,
                    'quantity' => (float)($quantities[$index] ?? 0),
                    'variant_id' => (int)($variantIds[$index] ?? 0),
                ];
            }
        }

        try {
            $id = $this->inventory->createTransaction(
                $type,
                $items,
                (int)service('session')->get('user_id'),
                [
                    'reference_no' => trim((string)$this->request->getPost('reference_no')) ?: null,
                    'party_name' => trim((string)$this->request->getPost('party_name')) ?: null,
                    'vehicle_no' => trim((string)$this->request->getPost('vehicle_no')) ?: null,
                    'remarks' => trim((string)$this->request->getPost('remarks')) ?: null,
                ]
            );

            return redirect()->to('/inventory')->with(
                'success',
                'Inventory ' . $type . ' transaction created successfully. ID: ' . $id
            );
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function detail(int $id)
    {
        $transaction = (new InventoryTransactionModel())->select('inventory_transactions.*, users.name AS user_name')->join('users','users.id=inventory_transactions.created_by','left')->find($id);
        if (!$transaction) return redirect()->to('/inventory/transactions')->with('error','Transaction not found.');
        $items = (new \App\Models\InventoryTransactionItemModel())->select('inventory_transaction_items.*, products.code, products.name, products.unit, product_variants.variant_name, product_variants.size_value, product_variants.size_unit')->join('products','products.id=inventory_transaction_items.product_id','left')->join('product_variants','product_variants.id=inventory_transaction_items.variant_id','left')->where('transaction_id',$id)->findAll();
        return view('inventory/detail',['title'=>'Transaction '.$transaction['transaction_no'],'transaction'=>$transaction,'items'=>$items]);
    }

    protected function productMap(): array
    {
        $map=[]; foreach($this->products->where('status',1)->findAll() as $row){ $map[(int)$row['id']] = ['measurement_type'=>strtoupper((string)($row['measurement_type']??'STANDARD')),'unit'=>(string)($row['unit']??'')]; }
        return $map;
    }

    protected function variantStockMap(): array
    {
        $map=[]; foreach($this->inventory->variantMap() as $id=>$variant){ $map[(int)$id]=$this->inventory->variantStock((int)$id); } return $map;
    }

    protected function stockMap(): array
    {
        $map = [];
        foreach ($this->inventory->stocks() as $row) {
            $map[(int)$row['id']] = (float)$row['current_stock'];
        }
        return $map;
    }

    public function transactions()
    {
        $model = new InventoryTransactionModel();

        $q = trim((string)$this->request->getGet('q'));
        $type = strtoupper(trim((string)$this->request->getGet('type')));
        $status = strtoupper(trim((string)$this->request->getGet('status')));
        $from = trim((string)$this->request->getGet('from'));
        $to = trim((string)$this->request->getGet('to'));
        $builder = $model->select('inventory_transactions.*, users.name AS user_name')->join('users', 'users.id = inventory_transactions.created_by', 'left');
        if ($q !== '') { $builder->groupStart()->like('transaction_no',$q)->orLike('reference_no',$q)->orLike('party_name',$q)->orLike('users.name',$q)->groupEnd(); }
        if (in_array($type,['IN','OUT'],true)) $builder->where('inventory_transactions.type',$type);
        if (in_array($status,['CONFIRMED','VOID'],true)) $builder->where('inventory_transactions.status',$status);
        if ($from !== '') $builder->where('inventory_transactions.created_at >=',$from.' 00:00:00');
        if ($to !== '') $builder->where('inventory_transactions.created_at <=',$to.' 23:59:59');
        $transactions = $builder->orderBy('inventory_transactions.id','DESC')->findAll();
        return view('inventory/transactions', [
            'title' => 'Inventory Transactions', 'transactions' => $transactions, 'canVoid' => (new AuthService())->can('inventory.void'),
            'q'=>$q,'type'=>$type,'status'=>$status,'from'=>$from,'to'=>$to,
        ]);
    }
}
