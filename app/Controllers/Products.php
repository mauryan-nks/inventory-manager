<?php

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Services\InventoryService;
use App\Services\AuditService;
use App\Services\AuthService;

class Products extends BaseController
{
    protected ProductModel $products;
    protected CategoryModel $categories;
    protected ProductVariantModel $variants;

    public function __construct()
    {
        $this->products = new ProductModel();
        $this->categories = new CategoryModel();
        $this->variants = new ProductVariantModel();
    }

    public function index()
    {
        $q = trim((string)$this->request->getGet('q'));
        $statusFilter = strtolower(trim((string)$this->request->getGet('status') ?: 'active'));
        if (!in_array($statusFilter, ['active', 'inactive', 'all'], true)) $statusFilter = 'active';

        $allCount = (int)(new ProductModel())->countAllResults();
        $activeCount = (int)(new ProductModel())->where('status', 1)->countAllResults();
        $inactiveCount = $allCount - $activeCount;

        $builder = (new ProductModel())->select('products.*, categories.name AS category_name')->join('categories', 'categories.id = products.category_id', 'left');
        if ($statusFilter === 'active') $builder->where('products.status', 1);
        if ($statusFilter === 'inactive') $builder->where('products.status', 0);
        if ($q !== '') {
            $builder->groupStart()->like('products.code', $q)->orLike('products.name', $q)->orLike('categories.name', $q)->groupEnd();
        }
        $products = $builder->orderBy('products.id', 'DESC')->findAll();

        $stocks = (new InventoryService())->stocks();
        $stockMap = [];
        $variantMap = [];

        foreach ($stocks as $stock) {
            $stockMap[(int)$stock['id']] = (float)$stock['current_stock'];
            $variantMap[(int)$stock['id']] = $stock['variants'] ?? [];
        }

        return view('products/index', [
            'title' => 'Products',
            'products' => $products,
            'q' => $q,
            'stockMap' => $stockMap,
            'variantMap' => $variantMap,
            'statusFilter' => $statusFilter,
            'allCount' => $allCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'authNav' => new AuthService(),
        ]);
    }

    public function create()
    {
        return view('products/form', [
            'title' => 'Add Product',
            'product' => null,
            'categories' => $this->categories->where('status', 1)->orderBy('name')->findAll(),
            'variants' => [],
        ]);
    }

    public function store()
    {
        $rules = [
            'code' => 'required|max_length[100]|is_unique[products.code]',
            'name' => 'required|max_length[200]',
            'unit' => 'required|max_length[50]',
            'minimum_stock' => 'permit_empty|integer|greater_than_equal_to[0]',
            'measurement_type' => 'required|in_list[STANDARD,LENGTH]',
            'category_id' => 'permit_empty|is_natural',
        ];
        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));

        $measurementType = strtoupper((string)$this->request->getPost('measurement_type'));
        $this->validateVariants($measurementType);
        $variants = $this->variantPayload($measurementType);
        if (!$variants) return redirect()->back()->withInput()->with('error', 'Add at least one product size/variant.');

        $openingTotal = array_sum(array_column($variants, 'opening_quantity'));
        $minimumTotal = array_sum(array_column($variants, 'minimum_quantity'));
        $this->products->insert([
            'category_id'=>$this->request->getPost('category_id') ?: null,
            'code'=>trim((string)$this->request->getPost('code')),
            'name'=>trim((string)$this->request->getPost('name')),
            'unit'=>trim((string)$this->request->getPost('unit')),
            'measurement_type'=>$measurementType,
            'stock_unit'=>'UNIT',
            'minimum_stock'=>$minimumTotal,
            'opening_stock'=>$openingTotal,
            'description'=>$this->request->getPost('description') ?: null,
            'variant_schema_json'=>$this->variantSchemaPayload(),
            'status'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
        ]);
        $newId=(int)$this->products->getInsertID();
        foreach ($variants as $variant) { $variant['product_id']=$newId; $this->variants->insert($variant); }
        (new AuditService())->record('CREATE_PRODUCT','products',$newId,'Product and variants created.');
        return redirect()->to('/products')->with('success','Product created successfully with '.count($variants).' variant(s).');
    }


    public function edit(int $id)
    {
        $product = $this->products->find($id);

        if (!$product) {
            return redirect()->to('/products')->with('error', 'Product not found.');
        }

        $product['opening_stock_display'] = (float)($product['opening_stock'] ?? 0);
        $product['minimum_stock_display'] = (float)($product['minimum_stock'] ?? 0);

        return view('products/form', [
            'title' => 'Edit Product',
            'product' => $product,
            'variants' => $this->variants->where('product_id', $id)->orderBy('id')->findAll(),
            'categories' => $this->categories->where('status', 1)->orderBy('name')->findAll(),
        ]);
    }

    public function update(int $id)
    {
        $product=$this->products->find($id);
        if (!$product) return redirect()->to('/products')->with('error','Product not found.');
        $rules=['code'=>'required|max_length[100]','name'=>'required|max_length[200]','unit'=>'required|max_length[50]','measurement_type'=>'required|in_list[STANDARD,LENGTH]','category_id'=>'permit_empty|is_natural'];
        if(!$this->validate($rules)) return redirect()->back()->withInput()->with('error',implode(' ',$this->validator->getErrors()));
        $measurementType=strtoupper((string)$this->request->getPost('measurement_type'));
        $this->validateVariants($measurementType);
        $variants=$this->variantPayload($measurementType);
        if(!$variants) return redirect()->back()->withInput()->with('error','Add at least one product size/variant.');
        $code=trim((string)$this->request->getPost('code'));
        $duplicate=$this->products->where('code',$code)->where('id !=',$id)->first();
        if($duplicate) return redirect()->back()->withInput()->with('error','Product code already exists.');

        // Once movement exists, variant opening quantities are locked to keep the ledger auditable.
        $hasMovements=(bool)$this->dbCheckMovement($id);
        $db=db_connect();
        $db->transBegin();
        try {
            $old=$product;
            $this->products->update($id,[
                'category_id'=>$this->request->getPost('category_id') ?: null,'code'=>$code,
                'name'=>trim((string)$this->request->getPost('name')),'unit'=>trim((string)$this->request->getPost('unit')),
                'measurement_type'=>$measurementType,'stock_unit'=>'UNIT',
                'minimum_stock'=>array_sum(array_column($variants,'minimum_quantity')),
                'opening_stock'=>$hasMovements ? (float)$product['opening_stock'] : array_sum(array_column($variants,'opening_quantity')),
                'description'=>trim((string)$this->request->getPost('description'))?:null,'variant_schema_json'=>$this->variantSchemaPayload(),'updated_at'=>date('Y-m-d H:i:s')
            ]);
            $existing=$this->variants->where('product_id',$id)->findAll();
            foreach($existing as $v) {
                if (!empty($v['id']) && !in_array((int)$v['id'], array_filter(array_map('intval',(array)$this->request->getPost('variant_id'))), true)) {
                    $this->variants->update((int)$v['id'],['status'=>0,'updated_at'=>date('Y-m-d H:i:s')]);
                }
            }
            foreach($variants as $variant) {
                $vid=(int)($variant['_id']??0); unset($variant['_id']);
                $variant['product_id']=$id; $variant['updated_at']=date('Y-m-d H:i:s');
                if($vid>0 && $this->variants->find($vid)) {
                    if($hasMovements) unset($variant['opening_quantity']);
                    $this->variants->update($vid,$variant);
                } else {
                    if ($hasMovements) $variant['opening_quantity'] = 0;
                    $this->variants->insert($variant);
                }
            }
            $db->transCommit();
            (new AuditService())->record('UPDATE_PRODUCT','products',$id,'Product and variants updated.',$old,$this->products->find($id));
            return redirect()->to('/products')->with('success','Product and variants updated successfully.');
        } catch(\Throwable $e) { $db->transRollback(); return redirect()->back()->withInput()->with('error',$e->getMessage()); }
    }


    protected function normalizeStockValue(float $value, string $measurementType, string $unit): float
    {
        return $value;
    }

    protected function validateVariants(string $measurementType): void
    {
        $names = $this->request->getPost('variant_name') ?? [];
        $opens = $this->request->getPost('variant_opening') ?? [];
        $attrs = $this->request->getPost('variant_attributes') ?? [];
        if (!is_array($names) || !is_array($opens) || !is_array($attrs)) {
            throw new \RuntimeException('Invalid variant data.');
        }
        foreach ($names as $i => $name) {
            if (trim((string)$name) === '') throw new \RuntimeException('Every variant needs a name.');
            if ((int)($opens[$i] ?? 0) < 0) throw new \RuntimeException('Opening quantity cannot be negative.');
            $raw = trim((string)($attrs[$i] ?? '{}'));
            $decoded = json_decode($raw === '' ? '{}' : $raw, true);
            if (!is_array($decoded)) throw new \RuntimeException('Variant attributes must contain valid JSON.');
        }
    }

    protected function variantPayload(string $measurementType): array
    {
        $ids = $this->request->getPost('variant_id') ?? [];
        $names = $this->request->getPost('variant_name') ?? [];
        $attrs = $this->request->getPost('variant_attributes') ?? [];
        $opens = $this->request->getPost('variant_opening') ?? [];
        $mins = $this->request->getPost('variant_minimum') ?? [];
        if (!is_array($names)) return [];
        $out = [];
        foreach ($names as $i => $name) {
            $name = trim((string)$name);
            if ($name === '') continue;
            $raw = trim((string)($attrs[$i] ?? '{}'));
            $decoded = json_decode($raw === '' ? '{}' : $raw, true);
            if (!is_array($decoded)) $decoded = [];

            // Backward compatibility: if an old form only supplied size fields, preserve them in JSON.
            $legacySize = $this->request->getPost('variant_size_value') ?? [];
            $legacyUnit = $this->request->getPost('variant_size_unit') ?? [];
            if (!$decoded && isset($legacySize[$i]) && $legacySize[$i] !== '') {
                $sv = (float)$legacySize[$i];
                $su = strtoupper((string)($legacyUnit[$i] ?? 'MM'));
                $decoded['size'] = ['value' => $sv, 'unit' => in_array($su, ['MM','IN'], true) ? $su : 'MM'];
            }
            $sizeValue = null; $sizeUnit = null; $sizeInches = null;
            if (isset($decoded['size']) && is_array($decoded['size']) && isset($decoded['size']['value'])) {
                $sizeValue = (float)$decoded['size']['value'];
                $sizeUnit = strtoupper((string)($decoded['size']['unit'] ?? 'MM'));
                if (in_array($sizeUnit, ['MM','IN'], true) && $sizeValue > 0) {
                    $sizeInches = $sizeUnit === 'MM' ? $sizeValue / 25.4 : $sizeValue;
                }
            }
            $out[] = [
                '_id' => (int)($ids[$i] ?? 0),
                'variant_name' => $name,
                'attributes_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'size_value' => $sizeValue,
                'size_unit' => $sizeUnit,
                'size_inches' => $sizeInches,
                'opening_quantity' => (int)($opens[$i] ?? 0),
                'minimum_quantity' => (int)($mins[$i] ?? 0),
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }
        return $out;
    }

    protected function variantSchemaPayload(): ?string
    {
        $raw = trim((string)$this->request->getPost('variant_schema_json'));
        if ($raw === '') return null;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return null;
        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function dbCheckMovement(int $productId): bool
    {
        return (int)db_connect()->table('inventory_transaction_items')->where('product_id',$productId)->countAllResults()>0;
    }

    public function delete(int $id)
    {
        $product = $this->products->find($id);

        if (!$product) {
            return redirect()->to('/products')->with('error', 'Product not found.');
        }

        // Keep transaction history intact; never physically delete a referenced product.
        $old = $product;
        $this->products->update($id, [
            'status' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        (new AuditService())->record('DEACTIVATE_PRODUCT','products',$id,'Product deactivated.',$old,$this->products->find($id));
        return redirect()->to('/products')->with('success', 'Product deactivated successfully.');
    }

    public function activate(int $id)
    {
        $product = $this->products->find($id);
        if (!$product) return redirect()->to('/products')->with('error', 'Product not found.');
        if ((int)$product['status'] === 1) return redirect()->to('/products')->with('success', 'Product is already active.');

        $old = $product;
        $this->products->update($id, ['status' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        (new AuditService())->record('ACTIVATE_PRODUCT', 'products', $id, 'Product activated.', $old, $this->products->find($id));
        return redirect()->to('/products')->with('success', 'Product activated successfully.');
    }

    public function hardDelete(int $id)
    {
        $product = $this->products->find($id);
        if (!$product) return redirect()->to('/products')->with('error', 'Product not found.');

        $movementCount = (int) db_connect()->table('inventory_transaction_items')->where('product_id', $id)->countAllResults();
        if ($movementCount > 0) {
            return redirect()->to('/products')->with('error', 'This product has transaction history and cannot be permanently deleted. Deactivate it instead.');
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $db->table('product_variants')->where('product_id', $id)->delete();
            $this->products->delete($id);
            if (!$db->transStatus()) throw new \RuntimeException('Could not permanently delete the product.');
            $db->transCommit();
            (new AuditService())->record('DELETE_PRODUCT', 'products', $id, 'Product permanently deleted because it had no transaction history.', $product, null);
            return redirect()->to('/products')->with('success', 'Product permanently deleted.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->to('/products')->with('error', $e->getMessage());
        }
    }

    public function categories()
    {
        return view('products/categories', [
            'title' => 'Categories',
            'categories' => $this->categories->orderBy('name')->findAll(),
        ]);
    }

    public function categoryStore()
    {
        $name = trim((string)$this->request->getPost('name'));

        if ($name === '') {
            return redirect()->back()->with('error', 'Category name is required.');
        }

        if ($this->categories->where('name', $name)->first()) {
            return redirect()->back()->with('error', 'Category already exists.');
        }

        $this->categories->insert([
            'name' => $name,
            'description' => $this->request->getPost('description') ?: null,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/products/categories')->with('success', 'Category created successfully.');
    }
}
