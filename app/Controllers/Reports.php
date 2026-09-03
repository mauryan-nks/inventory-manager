<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use App\Services\AuthService;

class Reports extends BaseController
{
    public function index()
    {
        $auth = new AuthService();
        $permissions = ['reports.stock','reports.in','reports.out','reports.security','reports.compare'];
        if (!array_reduce($permissions, fn($ok,$permission) => $ok || $auth->can($permission), false)) {
            return redirect()->to('/dashboard')->with('error', 'No report permissions are assigned to your account.');
        }
        return view('reports/index', ['title' => 'Reports', 'canStock'=>$auth->can('reports.stock'), 'canIn'=>$auth->can('reports.in'), 'canOut'=>$auth->can('reports.out'), 'canSecurity'=>$auth->can('reports.security'), 'canCompare'=>$auth->can('reports.compare')]);
    }

    public function stock()
    {
        $db = db_connect();
        $rows = $db->query("SELECT p.code,p.name,p.unit,p.measurement_type,p.stock_unit,p.opening_stock,p.minimum_stock,
            p.opening_stock + COALESCE(SUM(CASE WHEN t.status='CONFIRMED' AND t.type='IN' THEN i.quantity WHEN t.status='CONFIRMED' AND t.type='OUT' THEN -i.quantity ELSE 0 END),0) current_stock
            FROM products p LEFT JOIN inventory_transaction_items i ON i.product_id=p.id LEFT JOIN inventory_transactions t ON t.id=i.transaction_id
            GROUP BY p.id,p.code,p.name,p.unit,p.measurement_type,p.stock_unit,p.opening_stock,p.minimum_stock ORDER BY p.name")->getResultArray();
        return view('reports/stock', ['title' => 'Stock Report', 'rows' => $rows]);
    }

    public function movements(string $type)
    {
        $type = strtoupper($type);
        if (!in_array($type, ['IN', 'OUT'], true)) {
            return redirect()->to('/reports');
        }
        $from = trim((string)$this->request->getGet('from'));
        $to = trim((string)$this->request->getGet('to'));
        $product = trim((string)$this->request->getGet('product'));
        $supplier = trim((string)$this->request->getGet('party_name'));

        $db = db_connect();
        $builder = $db->table('inventory_transactions t')
            ->select("t.*, u.name AS user_name, GROUP_CONCAT(CONCAT(p.code,' · ',COALESCE(v.variant_name,''),' × ',CAST(i.quantity AS UNSIGNED),' ',p.unit) SEPARATOR ', ') AS items")
            ->join('users u','u.id=t.created_by','left')
            ->join('inventory_transaction_items i','i.transaction_id=t.id','left')
            ->join('products p','p.id=i.product_id','left')->join('product_variants v','v.id=i.variant_id','left')
            ->where('t.type',$type)
            ->groupBy('t.id')
            ->orderBy('t.id','DESC');
        if ($from !== '') $builder->where('t.created_at >=', $from . ' 00:00:00');
        if ($to !== '') $builder->where('t.created_at <=', $to . ' 23:59:59');
        if ($supplier !== '') $builder->like('t.party_name', $supplier);
        if ($product !== '') $builder->groupStart()->like('p.code',$product)->orLike('p.name',$product)->groupEnd();

        return view('reports/movements', [
            'title' => $type . ' Report', 'type' => $type, 'rows' => $builder->get()->getResultArray(),
            'from' => $from, 'to' => $to, 'product' => $product, 'party_name' => $supplier,
        ]);
    }

    public function compare()
    {
        $from = trim((string)$this->request->getGet('from'));
        $to = trim((string)$this->request->getGet('to'));
        $categoryId = (int)$this->request->getGet('category_id');
        $productId = (int)$this->request->getGet('product_id');
        $guardId = (int)$this->request->getGet('guard_id');
        $db = db_connect();

        $movement = $db->table('inventory_transaction_items i')
            ->select("p.id AS product_id, p.code, p.name, p.unit, c.name AS category_name,
                SUM(CASE WHEN t.type='IN' AND t.status='CONFIRMED' THEN i.quantity ELSE 0 END) AS total_in,
                SUM(CASE WHEN t.type='OUT' AND t.status='CONFIRMED' THEN i.quantity ELSE 0 END) AS total_out,
                SUM(CASE WHEN t.type='IN' AND t.status='CONFIRMED' THEN i.quantity WHEN t.type='OUT' AND t.status='CONFIRMED' THEN -i.quantity ELSE 0 END) AS net")
            ->join('inventory_transactions t','t.id=i.transaction_id','inner')
            ->join('products p','p.id=i.product_id','inner')
            ->join('categories c','c.id=p.category_id','left')
            ->groupBy('p.id,p.code,p.name,c.name')
            ->orderBy('p.name','ASC');
        if ($from !== '') $movement->where('t.created_at >=', $from.' 00:00:00');
        if ($to !== '') $movement->where('t.created_at <=', $to.' 23:59:59');
        if ($categoryId > 0) $movement->where('p.category_id', $categoryId);
        if ($productId > 0) $movement->where('p.id', $productId);
        $productRows = $movement->get()->getResultArray();

        $variantBuilder = $db->table('inventory_transaction_items i')
            ->select("p.id AS product_id, p.code, p.name, p.unit, v.id AS variant_id, v.variant_name, v.attributes_json, v.size_value, v.size_unit,
                SUM(CASE WHEN t.type='IN' AND t.status='CONFIRMED' THEN i.quantity ELSE 0 END) AS total_in,
                SUM(CASE WHEN t.type='OUT' AND t.status='CONFIRMED' THEN i.quantity ELSE 0 END) AS total_out,
                SUM(CASE WHEN t.type='IN' AND t.status='CONFIRMED' THEN i.quantity WHEN t.type='OUT' AND t.status='CONFIRMED' THEN -i.quantity ELSE 0 END) AS net")
            ->join('inventory_transactions t','t.id=i.transaction_id','inner')
            ->join('products p','p.id=i.product_id','inner')
            ->join('product_variants v','v.id=i.variant_id','left')
            ->groupBy('p.id,p.code,p.name,p.unit,v.id,v.variant_name,v.attributes_json,v.size_value,v.size_unit')
            ->orderBy('p.name','ASC')->orderBy('v.size_value','ASC');
        if ($from !== '') $variantBuilder->where('t.created_at >=', $from.' 00:00:00');
        if ($to !== '') $variantBuilder->where('t.created_at <=', $to.' 23:59:59');
        if ($categoryId > 0) $variantBuilder->where('p.category_id', $categoryId);
        if ($productId > 0) $variantBuilder->where('p.id', $productId);
        $variantRows = $variantBuilder->get()->getResultArray();

        $category = $db->table('inventory_transaction_items i')
            ->select("COALESCE(c.name,'Uncategorized') AS category_name, p.unit,
                SUM(CASE WHEN t.type='IN' AND t.status='CONFIRMED' THEN i.quantity ELSE 0 END) AS total_in,
                SUM(CASE WHEN t.type='OUT' AND t.status='CONFIRMED' THEN i.quantity ELSE 0 END) AS total_out,
                SUM(CASE WHEN t.type='IN' AND t.status='CONFIRMED' THEN i.quantity WHEN t.type='OUT' AND t.status='CONFIRMED' THEN -i.quantity ELSE 0 END) AS net")
            ->join('inventory_transactions t','t.id=i.transaction_id','inner')
            ->join('products p','p.id=i.product_id','inner')
            ->join('categories c','c.id=p.category_id','left')
            ->groupBy('p.category_id,c.name')
            ->orderBy('category_name','ASC');
        if ($from !== '') $category->where('t.created_at >=', $from.' 00:00:00');
        if ($to !== '') $category->where('t.created_at <=', $to.' 23:59:59');
        if ($categoryId > 0) $category->where('p.category_id', $categoryId);
        if ($productId > 0) $category->where('p.id', $productId);
        $categoryRows = $category->get()->getResultArray();

        // Security totals are attributed from incoming_documents.uploaded_by, not guessed from transaction creator.
        $guard = $db->table('inventory_transaction_items i')
            ->select("u.id AS guard_id, u.name AS guard_name, p.unit,
                SUM(CASE WHEN t.type='IN' AND t.status='CONFIRMED' THEN i.quantity ELSE 0 END) AS total_in,
                COUNT(DISTINCT CASE WHEN t.type='IN' AND t.status='CONFIRMED' THEN t.id END) AS incoming_transactions")
            ->join('inventory_transactions t','t.id=i.transaction_id','inner')
            ->join('incoming_documents d','d.transaction_id=t.id','inner')
            ->join('users u','u.id=d.uploaded_by','left')
            ->join('products p','p.id=i.product_id','inner')
            ->where('t.type','IN')->where('t.status','CONFIRMED')
            ->groupBy('u.id,u.name')
            ->orderBy('total_in','DESC');
        if ($from !== '') $guard->where('t.created_at >=', $from.' 00:00:00');
        if ($to !== '') $guard->where('t.created_at <=', $to.' 23:59:59');
        if ($categoryId > 0) $guard->where('p.category_id', $categoryId);
        if ($productId > 0) $guard->where('p.id', $productId);
        if ($guardId > 0) $guard->where('u.id', $guardId);
        $guardRows = $guard->get()->getResultArray();

        $categories = $db->table('categories')->where('status',1)->orderBy('name')->get()->getResultArray();
        $products = $db->table('products')->where('status',1)->orderBy('name')->get()->getResultArray();
        $guards = $db->table('users')->where('status',1)->whereIn('role',['security'])->orderBy('name')->get()->getResultArray();

        return view('reports/compare', compact('from','to','categoryId','productId','guardId','productRows','variantRows','categoryRows','guardRows','categories','products','guards') + ['title'=>'IN / OUT Comparison']);
    }

    public function security()
    {
        $db = db_connect();
        $rows = $db->table('incoming_documents d')
            ->select('d.*, u.name AS guard_name, t.transaction_no, t.reference_no, t.party_name')
            ->join('users u','u.id=d.uploaded_by','left')
            ->join('inventory_transactions t','t.id=d.transaction_id','left')
            ->orderBy('d.id','DESC')->get()->getResultArray();
        return view('reports/security', ['title' => 'Security Report', 'rows' => $rows]);
    }

    public function export(string $type): ResponseInterface
    {
        $type = strtoupper($type);
        $permission = $type === 'STOCK' ? 'reports.stock' : ($type === 'IN' ? 'reports.in' : ($type === 'OUT' ? 'reports.out' : 'reports.security'));
        if (!(new AuthService())->can($permission)) {
            return redirect()->to('/reports')->with('error', 'You do not have permission to export this report.');
        }
        if ($type === 'STOCK') {
            $db = db_connect();
            $rows = $db->query("SELECT p.code,p.name,p.unit,p.measurement_type,p.stock_unit,p.opening_stock,p.minimum_stock,
                p.opening_stock + COALESCE(SUM(CASE WHEN t.status='CONFIRMED' AND t.type='IN' THEN i.quantity WHEN t.status='CONFIRMED' AND t.type='OUT' THEN -i.quantity ELSE 0 END),0) current_stock
                FROM products p LEFT JOIN inventory_transaction_items i ON i.product_id=p.id LEFT JOIN inventory_transactions t ON t.id=i.transaction_id
                GROUP BY p.id,p.code,p.name,p.unit,p.measurement_type,p.stock_unit,p.opening_stock,p.minimum_stock ORDER BY p.name")->getResultArray();
            $header = ['Code','Product','Unit','Opening','Minimum','Current'];
            $data = array_map(fn($r) => [$r['code'],$r['name'],$r['unit'],$r['opening_stock'],$r['minimum_stock'],$r['current_stock']], $rows);
        } elseif (in_array($type, ['IN','OUT'], true)) {
            $rows = db_connect()->table('inventory_transactions t')->select("t.transaction_no,t.created_at,t.reference_no,t.party_name,t.vehicle_no,t.status,u.name AS user_name,GROUP_CONCAT(CONCAT(p.code,' · ',COALESCE(v.variant_name,''),' × ',CAST(i.quantity AS UNSIGNED),' ',p.unit) SEPARATOR ', ') AS items")->join('users u','u.id=t.created_by','left')->join('inventory_transaction_items i','i.transaction_id=t.id','left')->join('products p','p.id=i.product_id','left')->join('product_variants v','v.id=i.variant_id','left')->where('t.type',$type)->groupBy('t.id')->orderBy('t.id','DESC')->get()->getResultArray();
            $header = ['Transaction','Date/Time','Reference','Party','Vehicle','Status','User','Items'];
            $data = array_map(fn($r) => [$r['transaction_no'],$r['created_at'],$r['reference_no'],$r['party_name'],$r['vehicle_no'],$r['status'],$r['user_name'],$r['items']], $rows);
        } else {
            $rows = db_connect()->table('incoming_documents d')->select('d.created_at,d.original_filename,d.ocr_status,d.verified,u.name AS guard_name,t.transaction_no,t.reference_no,t.party_name')->join('users u','u.id=d.uploaded_by','left')->join('inventory_transactions t','t.id=d.transaction_id','left')->orderBy('d.id','DESC')->get()->getResultArray();
            $header = ['Date/Time','Document','OCR','Verified','Guard','Transaction','Reference','Supplier'];
            $data = array_map(fn($r) => [$r['created_at'],$r['original_filename'],$r['ocr_status'],$r['verified']?'Yes':'No',$r['guard_name'],$r['transaction_no'],$r['reference_no'],$r['party_name']], $rows);
        }

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $header);
        foreach ($data as $row) fputcsv($fp, $row);
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $this->response->setHeader('Content-Type','text/csv; charset=UTF-8')->setHeader('Content-Disposition','attachment; filename="inventory-'.$type.'-report-'.date('YmdHis').'.csv"')->setBody($csv);
    }
}
