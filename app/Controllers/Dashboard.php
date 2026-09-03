<?php

namespace App\Controllers;

use App\Models\InventoryTransactionModel;
use App\Models\ProductModel;
use App\Services\AuthService;
use App\Services\InventoryService;

class Dashboard extends BaseController
{
    public function index()
    {
        $auth = new AuthService();

        $data = [
            'title' => 'Dashboard',
            'userName' => service('session')->get('user_name'),
            'role' => service('session')->get('role'),
            'canIn' => $auth->can('inventory.in'),
            'canOut' => $auth->can('inventory.out'),
            'canProducts' => $auth->can('products.view'),
            'canUsers' => $auth->can('users.view'),
            'canSecurity' => $auth->can('security.scan') || $auth->can('security.manual_entry') || $auth->can('security.history'),
        ];

        // These models may be added by the next phase. Keep the dashboard
        // usable even before Products/Inventory controllers are installed.
        if (class_exists(ProductModel::class)) {
            $data['productCount'] = (new ProductModel())->where('status', 1)->countAllResults();
        } else {
            $data['productCount'] = 0;
        }

        if (class_exists(InventoryTransactionModel::class)) {
            $today = date('Y-m-d');
            $model = new InventoryTransactionModel();
            $data['todayIn'] = $model->where('type', 'IN')->where('status', 'CONFIRMED')->where('created_at >=', $today . ' 00:00:00')->countAllResults();
            $data['todayOut'] = $model->where('type', 'OUT')->where('status', 'CONFIRMED')->where('created_at >=', $today . ' 00:00:00')->countAllResults();
        } else {
            $data['todayIn'] = 0;
            $data['todayOut'] = 0;
        }

        $stocks = (new InventoryService())->stocks();
        $data['lowStock'] = count(array_filter($stocks, fn($r) => (float)$r['current_stock'] <= (float)$r['minimum_stock']));
        $data['currentUnits'] = array_reduce($stocks, fn($carry, $r) => $carry + max(0, (float)$r['current_stock']), 0.0);
        $data['lowItems'] = array_values(array_filter($stocks, fn($r) => (float)$r['current_stock'] <= (float)$r['minimum_stock']));
        $data['currentUnits'] = array_reduce($stocks, fn($carry, $r) => $carry + max(0, (float)$r['current_stock']), 0.0);
        $data['lowItems'] = array_values(array_filter($stocks, fn($r) => (float)$r['current_stock'] <= (float)$r['minimum_stock']));
        $data['recent'] = (new InventoryTransactionModel())->select('inventory_transactions.*, users.name AS user_name')->join('users','users.id=inventory_transactions.created_by','left')->orderBy('inventory_transactions.id','DESC')->findAll(8);

        return view('dashboard/index', $data);
    }
}
