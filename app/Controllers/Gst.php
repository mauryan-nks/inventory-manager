<?php
namespace App\Controllers;

use App\Services\GstService;

class Gst extends BaseController
{
    protected function adminOrInventory(): bool
    {
        $role=(string)session()->get('role');
        return $role==='admin' || in_array($this->request->getUri()->getPath(), ['gst/start','gst/refresh','gst/validate'], true) && (new \App\Services\AuthService())->can('inventory.view');
    }

    protected function guard()
    {
        if (!(new \App\Services\AuthService())->can('inventory.view') && (string)session()->get('role')!=='admin') {
            return $this->response->setJSON(['ok'=>false,'message'=>'You do not have permission to use GST validation.'])->setStatusCode(403);
        }
        return null;
    }

    public function start()
    {
        if ($r=$this->guard()) return $r;
        try { return $this->response->setJSON(['ok'=>true]+(new GstService())->start()); }
        catch(\Throwable $e){ return $this->response->setJSON(['ok'=>false,'message'=>$e->getMessage()])->setStatusCode(422); }
    }

    public function refresh()
    {
        if ($r=$this->guard()) return $r;
        $sessionId=trim((string)$this->request->getGet('sessionId'));
        try { return $this->response->setJSON(['ok'=>true]+(new GstService())->refresh($sessionId)); }
        catch(\Throwable $e){ return $this->response->setJSON(['ok'=>false,'message'=>$e->getMessage()])->setStatusCode(422); }
    }

    public function validateGst()
    {
        if ($r=$this->guard()) return $r;
        $gstin=strtoupper(trim((string)$this->request->getPost('gstin')));
        $sessionId=trim((string)$this->request->getPost('sessionId'));
        $captcha=trim((string)$this->request->getPost('captcha'));
        if(!preg_match('/^[0-9A-Z]{15}$/',$gstin)) return $this->response->setJSON(['ok'=>false,'message'=>'Enter a valid 15-character GSTIN.'])->setStatusCode(422);
        try {
            $result=(new GstService())->validate($sessionId,$gstin,$captcha);
            session()->set('gst_validation_'.hash('sha256',$gstin), ['valid'=>true,'validated_at'=>time(),'data'=>$result['data']??null]);
            return $this->response->setJSON(['ok'=>true,'message'=>'GSTIN validated successfully.','data'=>$result['data']??null]);
        } catch(\Throwable $e){
            session()->remove('gst_validation_'.hash('sha256',$gstin));
            return $this->response->setJSON(['ok'=>false,'message'=>$e->getMessage()])->setStatusCode(422);
        }
    }
}
