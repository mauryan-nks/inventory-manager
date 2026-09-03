<?php
namespace App\Controllers;
use App\Models\CategoryModel;
use App\Models\FactoryProductionModel;
use App\Models\ProductModel;
use App\Services\AuthService;
use App\Services\InventoryService;
use App\Services\AuditService;
use RuntimeException;
class Production extends BaseController
{
    public function index()
    {
        if (!$this->enabled()) return redirect()->to('/dashboard')->with('error','Factory Production is disabled in Settings.');
        $rows=(new FactoryProductionModel())->select('factory_productions.*, products.code, products.name AS product_name, categories.name AS category_name, users.name AS user_name')
            ->join('products','products.id=factory_productions.product_id','left')->join('categories','categories.id=factory_productions.category_id','left')->join('users','users.id=factory_productions.created_by','left')
            ->orderBy('production_date','DESC')->orderBy('factory_productions.id','DESC')->findAll();
        return view('production/index',['title'=>'Factory Production','rows'=>$rows,'authNav'=>new AuthService()]);
    }
    public function create()
    {
        if (!$this->enabled()) return redirect()->to('/dashboard')->with('error','Factory Production is disabled in Settings.');
        return view('production/form',['title'=>'Factory Production','products'=>(new ProductModel())->select('products.*, categories.name AS category_name')->join('categories','categories.id=products.category_id','left')->where('products.status',1)->orderBy('products.name')->findAll(),'categories'=>(new CategoryModel())->where('status',1)->orderBy('name')->findAll()]);
    }
    public function store()
    {
        if (!$this->enabled()) return redirect()->to('/dashboard')->with('error','Factory Production is disabled in Settings.');
        if (!(new AuthService())->can('inventory.in')) return redirect()->to('/dashboard')->with('error','You do not have permission to record factory production.');
        $date=trim((string)$this->request->getPost('production_date')) ?: date('Y-m-d');
        $productId=(int)$this->request->getPost('product_id'); $categoryId=(int)$this->request->getPost('category_id'); $quantity=trim((string)$this->request->getPost('quantity'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !strtotime($date)) return redirect()->back()->withInput()->with('error','Please enter a valid production date.');
        if ($productId<1 || $categoryId<1 || $quantity==='' || !ctype_digit($quantity) || (int)$quantity<1) return redirect()->back()->withInput()->with('error','Item, category and a whole-number quantity greater than 0 are required.');
        $product=(new ProductModel())->select('products.*, categories.name AS category_name')->join('categories','categories.id=products.category_id','left')->find($productId);
        $category=(new CategoryModel())->where('id',$categoryId)->where('status',1)->first();
        if (!$product || (int)$product['status']!==1 || !$category) return redirect()->back()->withInput()->with('error','Selected item or category is invalid.');
        try {
            $inventory=new InventoryService();
            $txId=$inventory->createTransaction('IN',[[ 'product_id'=>$productId,'variant_id'=>$this->defaultVariant($productId),'quantity'=>(int)$quantity ]],(int)session()->get('user_id'),['reference_no'=>'FACTORY-'.date('YmdHis'),'party_name'=>'Factory Production','remarks'=>'Factory production dated '.$date]);
            (new FactoryProductionModel())->insert(['production_date'=>$date,'product_id'=>$productId,'category_id'=>$categoryId,'item_name'=>$product['name'],'quantity'=>(int)$quantity,'transaction_id'=>$txId,'created_by'=>(int)session()->get('user_id'),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            (new AuditService())->record('CREATE_FACTORY_PRODUCTION','factory_productions',null,'Factory production recorded.',null,['product_id'=>$productId,'quantity'=>(int)$quantity,'production_date'=>$date]);
            return redirect()->to('/production')->with('success','Factory production recorded and added to stock.');
        } catch (RuntimeException $e) { return redirect()->back()->withInput()->with('error',$e->getMessage()); }
    }
    protected function defaultVariant(int $productId): int
    {
        $v=db_connect()->table('product_variants')->where('product_id',$productId)->where('status',1)->orderBy('id','ASC')->get()->getRowArray();
        if (!$v) throw new RuntimeException('This product has no active variant. Add a variant to the product before recording factory production.');
        return (int)$v['id'];
    }
    protected function enabled(): bool { $v=(new \App\Models\SettingModel())->value('factory_production_enabled','0'); return (string)$v==='1'; }
}
