<?php

namespace App\Controllers;

use App\Models\InventoryTransactionItemModel;
use App\Models\InventoryTransactionModel;
use App\Services\AuditService;

class Transactions extends BaseController
{
    public function void(int $id)
    {
        $model = new InventoryTransactionModel();
        $transaction = $model->find($id);
        if (!$transaction) return redirect()->to('/inventory/transactions')->with('error','Transaction not found.');
        if ($transaction['status'] === 'VOID') return redirect()->to('/inventory/transactions')->with('error','Transaction is already void.');

        $reason = trim((string)$this->request->getPost('reason'));
        if ($reason === '') return redirect()->to('/inventory/transactions')->with('error','A void reason is required.');

        $old = $transaction;
        $transaction['status'] = 'VOID';
        $transaction['remarks'] = trim(($transaction['remarks'] ?? '') . "\nVOID: " . $reason);
        $transaction['updated_at'] = date('Y-m-d H:i:s');
        $model->update($id, ['status'=>'VOID','remarks'=>$transaction['remarks'],'updated_at'=>$transaction['updated_at']]);
        (new AuditService())->record('VOID_TRANSACTION','inventory',$id,'Inventory transaction voided.',$old,$transaction);
        return redirect()->to('/inventory/transactions')->with('success','Transaction voided. Stock has been recalculated without it.');
    }
}
