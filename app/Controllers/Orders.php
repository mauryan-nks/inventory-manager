<?php
namespace App\Controllers;

use App\Models\OrderDeliveryModel;
use App\Models\OrderDocumentModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderPartyModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\GstService;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

class Orders extends BaseController
{
    protected OrderPartyModel $parties;
    protected OrderModel $orders;
    protected OrderItemModel $items;
    protected OrderDeliveryModel $deliveries;
    protected OrderDocumentModel $documents;

    public function __construct()
    {
        $this->parties = new OrderPartyModel();
        $this->orders = new OrderModel();
        $this->items = new OrderItemModel();
        $this->deliveries = new OrderDeliveryModel();
        $this->documents = new OrderDocumentModel();
    }

    protected function adminOnly()
    {
        if ((string)service('session')->get('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Orders module is available to Admin only.');
        }
        return null;
    }

    public function index()
    {
        if ($r = $this->adminOnly()) return $r;
        $q = trim((string)$this->request->getGet('q'));
        $type = strtoupper(trim((string)$this->request->getGet('type')));
        if (!in_array($type, ['GOV','PRIVATE','LOCAL'], true)) $type = '';

        $parties = $this->partyRows($q, $type);
        $stats = $this->stats();
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['ok'=>true,'html'=>view('orders/_party_rows',['parties'=>$parties]),'count'=>count($parties),'stats'=>$stats]);
        }
        return view('orders/index', ['title'=>'Orders Dashboard','parties'=>$parties,'q'=>$q,'type'=>$type,'stats'=>$stats,'authNav'=>new AuthService()]);
    }

    public function partyCreate()
    {
        if ($r = $this->adminOnly()) return $r;
        return view('orders/party_form',['title'=>'Add Party','party'=>null]);
    }

    public function partyStore()
    {
        if ($r = $this->adminOnly()) return $r;
        $rules=['party_type'=>'required|in_list[GOV,PRIVATE,LOCAL]','name'=>'required|max_length[200]','code'=>'permit_empty|max_length[80]','contact_person'=>'permit_empty|max_length[150]','phone'=>'permit_empty|max_length[50]','email'=>'permit_empty|valid_email|max_length[190]','gstin'=>'permit_empty|max_length[15]','state'=>'permit_empty|max_length[100]','pincode'=>'permit_empty|max_length[10]','address'=>'permit_empty'];
        if(!$this->validate($rules)) return redirect()->back()->withInput()->with('error',implode(' ',$this->validator->getErrors()));
        $gstin=strtoupper(trim((string)$this->request->getPost('gstin')));
        if($gstin!=='' && !(new GstService())->wasValidated($gstin)) return redirect()->back()->withInput()->with('error','Please validate the GSTIN with the GST API before saving this party.');
        $data=$this->partyPayload(); $data['created_by']=(int)session()->get('user_id'); $data['created_at']=date('Y-m-d H:i:s'); $data['updated_at']=date('Y-m-d H:i:s');
        $id=$this->parties->insert($data,true);
        (new AuditService())->record('CREATE_ORDER_PARTY','order_parties',(int)$id,'Order party created.',null,$data);
        return redirect()->to('/orders')->with('success','Party created successfully.');
    }

    public function partyEdit(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $party=$this->parties->find($id); if(!$party) return redirect()->to('/orders')->with('error','Party not found.');
        return view('orders/party_form',['title'=>'Edit Party','party'=>$party]);
    }

    public function partyUpdate(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $party=$this->parties->find($id); if(!$party) return redirect()->to('/orders')->with('error','Party not found.');
        $rules=['party_type'=>'required|in_list[GOV,PRIVATE,LOCAL]','name'=>'required|max_length[200]','code'=>'permit_empty|max_length[80]','contact_person'=>'permit_empty|max_length[150]','phone'=>'permit_empty|max_length[50]','email'=>'permit_empty|valid_email|max_length[190]','gstin'=>'permit_empty|max_length[15]','state'=>'permit_empty|max_length[100]','pincode'=>'permit_empty|max_length[10]','address'=>'permit_empty'];
        if(!$this->validate($rules)) return redirect()->back()->withInput()->with('error',implode(' ',$this->validator->getErrors()));
        $gstin=strtoupper(trim((string)$this->request->getPost('gstin')));
        if($gstin!=='' && !(new GstService())->wasValidated($gstin)) return redirect()->back()->withInput()->with('error','Please validate the GSTIN with the GST API before saving this party.');
        $data=$this->partyPayload(); $data['updated_at']=date('Y-m-d H:i:s'); $this->parties->update($id,$data);
        (new AuditService())->record('UPDATE_ORDER_PARTY','order_parties',$id,'Order party updated.',$party,$data);
        return redirect()->to('/orders')->with('success','Party updated successfully.');
    }

    public function partyToggle(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $party=$this->parties->find($id); if(!$party) return $this->response->setJSON(['ok'=>false,'message'=>'Party not found.'])->setStatusCode(404);
        $new=(int)$party['status']===1?0:1; $this->parties->update($id,['status'=>$new,'updated_at'=>date('Y-m-d H:i:s')]);
        (new AuditService())->record('TOGGLE_ORDER_PARTY','order_parties',$id,'Order party status changed.',$party,['status'=>$new]);
        return $this->response->setJSON(['ok'=>true,'status'=>$new]);
    }

    public function partyOrders(int $partyId)
    {
        if ($r = $this->adminOnly()) return $r;
        $party=$this->parties->find($partyId); if(!$party) return redirect()->to('/orders')->with('error','Party not found.');
        $q=trim((string)$this->request->getGet('q')); $status=strtoupper(trim((string)$this->request->getGet('status')));
        $orders=$this->orderRows($partyId,$q,$status);
        if($this->request->isAJAX()) return $this->response->setJSON(['ok'=>true,'html'=>view('orders/_order_rows',['orders'=>$orders]),'count'=>count($orders)]);
        return view('orders/orders', ['title'=>$party['name'].' · Orders','party'=>$party,'orders'=>$orders,'q'=>$q,'status'=>$status,'authNav'=>new AuthService()]);
    }

    public function create(int $partyId)
    {
        if ($r = $this->adminOnly()) return $r;
        $party=$this->parties->where('id',$partyId)->where('status',1)->first(); if(!$party) return redirect()->to('/orders')->with('error','Party not found or inactive.');
        return view('orders/form',['title'=>'Create Order','party'=>$party,'order'=>null,'items'=>[],'products'=>$this->products(),'variantMap'=>$this->variantMap()]);
    }

    public function store()
    {
        if ($r = $this->adminOnly()) return $r;
        $partyId=(int)$this->request->getPost('party_id');
        $party=$this->parties->where('id',$partyId)->where('status',1)->first(); if(!$party) return redirect()->back()->withInput()->with('error','Please select a valid active party.');
        $data=$this->validateOrderHeader(); if($data===false) return redirect()->back()->withInput();
        $itemPayload=$this->itemPayload(); if(!$itemPayload) return redirect()->back()->withInput()->with('error','Add at least one order item with quantity greater than 0.');
        $db=db_connect(); $db->transBegin();
        try {
            $data['order_no']=$this->nextOrderNo(); $data['party_id']=$partyId; $data['status']='OPEN'; $data['created_by']=(int)session()->get('user_id'); $data['created_at']=date('Y-m-d H:i:s'); $data['updated_at']=date('Y-m-d H:i:s');
            $this->orders->insert($data); $id=(int)$this->orders->getInsertID();
            foreach($itemPayload as $item){$item['order_id']=$id;$item['created_at']=date('Y-m-d H:i:s');$item['updated_at']=date('Y-m-d H:i:s');$this->items->insert($item);}
            $db->transCommit();
            (new AuditService())->record('CREATE_ORDER','orders',$id,'Order created.',null,['order_no'=>$data['order_no'],'party_id'=>$partyId,'items'=>$itemPayload]);
            return redirect()->to('/orders/party/'.$partyId)->with('success','Order '.$data['order_no'].' created successfully.');
        } catch(\Throwable $e){$db->transRollback();return redirect()->back()->withInput()->with('error',$e->getMessage());}
    }

    public function edit(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $order=$this->orders->find($id); if(!$order) return redirect()->to('/orders')->with('error','Order not found.');
        $party=$this->parties->find((int)$order['party_id']); $items=$this->items->where('order_id',$id)->orderBy('id')->findAll();
        return view('orders/form',['title'=>'Edit '.$order['order_no'],'party'=>$party,'order'=>$order,'items'=>$items,'products'=>$this->products(),'variantMap'=>$this->variantMap()]);
    }

    public function update(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $order=$this->orders->find($id); if(!$order) return redirect()->to('/orders')->with('error','Order not found.');
        $data=$this->validateOrderHeader(); if($data===false) return redirect()->back()->withInput();
        $hasDeliveries=(int)$this->deliveries->where('order_id',$id)->countAllResults()>0;
        $itemPayload=$this->itemPayload();
        if(!$hasDeliveries && !$itemPayload) return redirect()->back()->withInput()->with('error','Add at least one order item with quantity greater than 0.');
        $db=db_connect();$db->transBegin();
        try{
            $data['updated_at']=date('Y-m-d H:i:s');
            $this->orders->update($id,$data);
            if(!$hasDeliveries){
                $this->items->where('order_id',$id)->delete();
                foreach($itemPayload as $item){$item['order_id']=$id;$item['created_at']=date('Y-m-d H:i:s');$item['updated_at']=date('Y-m-d H:i:s');$this->items->insert($item);}
                $this->recalculateStatus($id);
            }
            $db->transCommit();
            (new AuditService())->record('UPDATE_ORDER','orders',$id,$hasDeliveries?'Order header updated; item quantities were locked because delivery history exists.':'Order updated.',$order,$data);
            return redirect()->to('/orders/'.$id)->with('success',$hasDeliveries?'Order details updated. Item quantities remain locked because delivery history exists.':'Order updated successfully.');
        }
        catch(\Throwable $e){$db->transRollback();return redirect()->back()->withInput()->with('error',$e->getMessage());}
    }

    public function viewOrder(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $order=$this->orderDetail($id); if(!$order) return redirect()->to('/orders')->with('error','Order not found.');
        $items=$this->items->where('order_id',$id)->orderBy('id')->findAll();
        $deliveries=$this->deliveryRows($id); $docs=$this->documents->where('order_id',$id)->orderBy('id','DESC')->findAll();
        $deliveredMap=[]; foreach($deliveries as $d){$k=(int)$d['order_item_id'];$deliveredMap[$k]=($deliveredMap[$k]??0)+(float)$d['quantity'];}
        foreach($items as &$it){$it['delivered_quantity']=$deliveredMap[(int)$it['id']]??0;$it['remaining_quantity']=max(0,(float)$it['quantity']-(float)$it['delivered_quantity']);}
        return view('orders/view',['title'=>$order['order_no'],'order'=>$order,'items'=>$items,'deliveries'=>$deliveries,'docs'=>$docs]);
    }

    public function deliveryStore(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $order=$this->orders->find($id); if(!$order) return redirect()->to('/orders')->with('error','Order not found.');
        $itemId=(int)$this->request->getPost('order_item_id');$item=$this->items->where('id',$itemId)->where('order_id',$id)->first();
        $date=trim((string)$this->request->getPost('delivery_date'));$qty=trim((string)$this->request->getPost('quantity'));
        if(!$item || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || !$this->validNumber($qty) || (float)$qty<=0) return redirect()->to('/orders/'.$id)->with('error','Please enter a valid delivery item, date and quantity.');
        $delivered=(float)$this->deliveries->selectSum('quantity')->where('order_item_id',$itemId)->first()['quantity'];$remaining=(float)$item['quantity']-$delivered;
        if((float)$qty>$remaining+0.000001) return redirect()->to('/orders/'.$id)->with('error','Delivery quantity cannot exceed the remaining order quantity.');
        $payload=['order_id'=>$id,'order_item_id'=>$itemId,'delivery_date'=>$date,'quantity'=>(float)$qty,'reference_no'=>trim((string)$this->request->getPost('reference_no'))?:null,'remarks'=>trim((string)$this->request->getPost('remarks'))?:null,'created_by'=>(int)session()->get('user_id'),'created_at'=>date('Y-m-d H:i:s')];
        $this->deliveries->insert($payload);$this->recalculateStatus($id);(new AuditService())->record('CREATE_ORDER_DELIVERY','order_deliveries',(int)$this->deliveries->getInsertID(),'Order delivery recorded.',null,$payload);
        return redirect()->to('/orders/'.$id)->with('success','Delivery recorded successfully.');
    }

    public function deliveryDelete(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $d=$this->deliveries->find($id);if(!$d)return $this->response->setJSON(['ok'=>false,'message'=>'Delivery not found.'])->setStatusCode(404);
        $this->deliveries->delete($id);$this->recalculateStatus((int)$d['order_id']);(new AuditService())->record('DELETE_ORDER_DELIVERY','order_deliveries',$id,'Order delivery deleted.',$d);return $this->response->setJSON(['ok'=>true]);
    }

    public function delete(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $order=$this->orders->find($id);if(!$order)return $this->response->setJSON(['ok'=>false,'message'=>'Order not found.'])->setStatusCode(404);
        $partyId=(int)$order['party_id'];$db=db_connect();$db->transBegin();
        try{
            $docs=$this->documents->where('order_id',$id)->findAll();
            foreach($docs as $doc){$path=WRITEPATH.'uploads/'.$doc['file_path'];if(is_file($path))@unlink($path);}
            $this->documents->where('order_id',$id)->delete();$this->deliveries->where('order_id',$id)->delete();$this->items->where('order_id',$id)->delete();$this->orders->delete($id);$db->transCommit();
            (new AuditService())->record('DELETE_ORDER','orders',$id,'Order deleted.',$order);
            return $this->response->setJSON(['ok'=>true,'redirect'=>site_url('orders/party/'.$partyId)]);
        }catch(\Throwable $e){$db->transRollback();return $this->response->setJSON(['ok'=>false,'message'=>'Unable to delete order.'])->setStatusCode(500);}
    }

    public function files(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $order=$this->orderDetail($id);if(!$order)return redirect()->to('/orders')->with('error','Order not found.');
        return view('orders/files',['title'=>$order['order_no'].' · Files','order'=>$order,'docs'=>$this->documents->where('order_id',$id)->orderBy('id','DESC')->findAll()]);
    }

    public function fileUpload(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $order=$this->orders->find($id);if(!$order)return redirect()->to('/orders')->with('error','Order not found.');
        $stage=strtoupper(trim((string)$this->request->getPost('stage')));if(!in_array($stage,['PURCHASE_ORDER','AGREEMENT','DELIVERY','OTHER'],true))return redirect()->to('/orders/'.$id.'/files')->with('error','Invalid document stage.');
        $file=$this->request->getFile('document');if(!$file instanceof UploadedFile || !$file->isValid())return redirect()->to('/orders/'.$id.'/files')->with('error','Please select a valid file.');
        if($file->getSize()>15*1024*1024)return redirect()->to('/orders/'.$id.'/files')->with('error','Maximum file size is 15 MB.');
        $allowed=['pdf','jpg','jpeg','png','webp','doc','docx','xls','xlsx','txt'];$ext=strtolower($file->getClientExtension());if(!in_array($ext,$allowed,true))return redirect()->to('/orders/'.$id.'/files')->with('error','Allowed files: PDF, images, DOC/DOCX, XLS/XLSX and TXT.');
        $dir=WRITEPATH.'uploads/order_documents';if(!is_dir($dir))mkdir($dir,0775,true);$safe='ORD_'.$id.'_'.date('YmdHis').'_'.bin2hex(random_bytes(5)).'.'.$ext;$file->move($dir,$safe);
        $payload=['order_id'=>$id,'stage'=>$stage,'file_name'=>$file->getClientName(),'file_path'=>'order_documents/'.$safe,'mime_type'=>$file->getClientMimeType(),'file_size'=>$file->getSize(),'created_by'=>(int)session()->get('user_id'),'created_at'=>date('Y-m-d H:i:s')];$this->documents->insert($payload);(new AuditService())->record('UPLOAD_ORDER_DOCUMENT','order_documents',(int)$this->documents->getInsertID(),'Order document uploaded.',null,$payload);
        return redirect()->to('/orders/'.$id.'/files')->with('success','File uploaded successfully.');
    }

    public function fileDownload(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $doc=$this->documents->find($id);if(!$doc)return redirect()->to('/orders')->with('error','File not found.');$path=WRITEPATH.'uploads/'.$doc['file_path'];if(!is_file($path))return redirect()->to('/orders/'.$doc['order_id'].'/files')->with('error','Stored file is missing.');return $this->response->download($path,null)->setFileName($doc['file_name']);
    }

    public function fileDelete(int $id)
    {
        if ($r = $this->adminOnly()) return $r;
        $doc=$this->documents->find($id);if(!$doc)return $this->response->setJSON(['ok'=>false,'message'=>'File not found.'])->setStatusCode(404);$path=WRITEPATH.'uploads/'.$doc['file_path'];if(is_file($path))@unlink($path);$this->documents->delete($id);(new AuditService())->record('DELETE_ORDER_DOCUMENT','order_documents',$id,'Order document deleted.',$doc);return $this->response->setJSON(['ok'=>true]);
    }

    protected function partyRows(string $q='', string $type=''): array
    {
        $b=$this->parties->where('status',1);if($type!=='')$b->where('party_type',$type);if($q!=='')$b->groupStart()->like('name',$q)->orLike('code',$q)->orLike('contact_person',$q)->orLike('phone',$q)->orLike('gstin',$q)->orLike('state',$q)->orLike('pincode',$q)->groupEnd();$rows=$b->orderBy('name','ASC')->findAll();
        foreach($rows as &$r){$r['order_count']=(int)$this->orders->where('party_id',$r['id'])->countAllResults();}
        return $rows;
    }

    protected function orderRows(int $partyId,string $q='',string $status=''): array
    {
        $b=$this->orders->select('orders.*, order_parties.name AS party_name, order_parties.party_type')->join('order_parties','order_parties.id=orders.party_id','left')->where('orders.party_id',$partyId);if($status!=='')$b->where('orders.status',$status);if($q!=='')$b->groupStart()->like('orders.order_no',$q)->orLike('orders.notes',$q)->orLike('orders.status',$q)->groupEnd();$rows=$b->orderBy('orders.order_date','DESC')->orderBy('orders.id','DESC')->findAll();
        foreach($rows as &$r){$r['ordered_qty']=(float)$this->items->selectSum('quantity')->where('order_id',$r['id'])->first()['quantity'];$r['delivered_qty']=(float)$this->deliveries->selectSum('quantity')->where('order_id',$r['id'])->first()['quantity'];$r['remaining_qty']=max(0,$r['ordered_qty']-$r['delivered_qty']);}
        return $rows;
    }

    protected function stats(): array
    {
        $stats=['parties'=>$this->parties->where('status',1)->countAllResults(),'gov'=>$this->parties->where('status',1)->where('party_type','GOV')->countAllResults(),'private'=>$this->parties->where('status',1)->where('party_type','PRIVATE')->countAllResults(),'local'=>$this->parties->where('status',1)->where('party_type','LOCAL')->countAllResults(),'orders'=>$this->orders->countAllResults()];
        $stats['ordered']=(float)($this->items->selectSum('quantity')->first()['quantity']??0);$stats['delivered']=(float)($this->deliveries->selectSum('quantity')->first()['quantity']??0);$stats['remaining']=max(0,$stats['ordered']-$stats['delivered']);return $stats;
    }

    protected function orderDetail(int $id): ?array
    {
        return $this->orders->select('orders.*, order_parties.name AS party_name, order_parties.party_type, order_parties.code AS party_code, order_parties.contact_person, order_parties.phone, order_parties.email, order_parties.gstin, order_parties.state, order_parties.pincode, order_parties.address')->join('order_parties','order_parties.id=orders.party_id','left')->where('orders.id',$id)->first();
    }

    protected function deliveryRows(int $orderId): array
    {
        return $this->deliveries->select('order_deliveries.*, order_items.item_name, order_items.variant_name')->join('order_items','order_items.id=order_deliveries.order_item_id','left')->where('order_deliveries.order_id',$orderId)->orderBy('delivery_date','DESC')->orderBy('order_deliveries.id','DESC')->findAll();
    }

    protected function products(): array
    {
        return (new ProductModel())->select('products.id,products.code,products.name,products.unit,categories.name AS category_name')->join('categories','categories.id=products.category_id','left')->where('products.status',1)->orderBy('products.name')->findAll();
    }

    protected function variantMap(): array
    {
        $rows=(new ProductVariantModel())->where('status',1)->orderBy('id')->findAll();$map=[];foreach($rows as $v)$map[(int)$v['id']]=$v;return $map;
    }

    protected function partyPayload(): array{return ['party_type'=>strtoupper(trim((string)$this->request->getPost('party_type'))),'name'=>trim((string)$this->request->getPost('name')),'code'=>trim((string)$this->request->getPost('code'))?:null,'contact_person'=>trim((string)$this->request->getPost('contact_person'))?:null,'phone'=>trim((string)$this->request->getPost('phone'))?:null,'email'=>trim((string)$this->request->getPost('email'))?:null,'gstin'=>strtoupper(trim((string)$this->request->getPost('gstin')))?:null,'state'=>trim((string)$this->request->getPost('state'))?:null,'pincode'=>trim((string)$this->request->getPost('pincode'))?:null,'address'=>trim((string)$this->request->getPost('address'))?:null,'status'=>1];}

    protected function validateOrderHeader(): array|false
    {
        $rules=['order_date'=>'required|valid_date[Y-m-d]','delivery_start_date'=>'permit_empty|valid_date[Y-m-d]','delivery_end_date'=>'permit_empty|valid_date[Y-m-d]','notes'=>'permit_empty'];if(!$this->validate($rules)){redirect()->back()->withInput()->with('error',implode(' ',$this->validator->getErrors()));return false;}$start=trim((string)$this->request->getPost('delivery_start_date'));$end=trim((string)$this->request->getPost('delivery_end_date'));if($start!==''&&$end!==''&&$start>$end){redirect()->back()->withInput()->with('error','Delivery end date cannot be before delivery start date.');return false;}return ['order_date'=>trim((string)$this->request->getPost('order_date')),'delivery_start_date'=>$start?:null,'delivery_end_date'=>$end?:null,'notes'=>trim((string)$this->request->getPost('notes'))?:null];
    }

    protected function itemPayload(): array
    {
        $p=(array)$this->request->getPost('product_id');$v=(array)$this->request->getPost('variant_id');$q=(array)$this->request->getPost('quantity');$out=[];foreach($p as $i=>$pid){$pid=(int)$pid;$vid=(int)($v[$i]??0);$qty=trim((string)($q[$i]??''));if($pid<1||$vid<1||!$this->validNumber($qty)||(float)$qty<=0)continue;$product=(new ProductModel())->find($pid);$variant=(new ProductVariantModel())->where('id',$vid)->where('product_id',$pid)->where('status',1)->first();if(!$product||!$variant)continue;$out[]=['product_id'=>$pid,'variant_id'=>$vid,'item_name'=>$product['name'],'variant_name'=>$this->variantLabel($variant),'quantity'=>(float)$qty];}return $out;
    }

    protected function variantLabel(array $v): string
    { $parts=[];if(!empty($v['variant_name']))$parts[]=$v['variant_name'];if(!empty($v['size_value']))$parts[]=trim((string)$v['size_value'].' '.(string)($v['size_unit']??''));if(!empty($v['attributes_json'])){try{$a=json_decode((string)$v['attributes_json'],true,512,JSON_THROW_ON_ERROR);foreach((array)$a as $k=>$val){if(is_array($val)&&array_key_exists('value',$val))$val=(string)$val['value'].' '.(string)($val['unit']??'');$parts[]=ucwords(str_replace(['_','-'],' ',(string)$k)).': '.$val;}}catch(\Throwable $e){}}return implode(' · ',array_unique(array_filter($parts)))?:'Default variant'; }

    protected function nextOrderNo(): string
    { $row=$this->orders->select('order_no')->orderBy('id','DESC')->first();$n=1;if($row&&preg_match('/ORD-(\d+)/',$row['order_no'],$m))$n=(int)$m[1]+1;return 'ORD-'.str_pad((string)$n,4,'0',STR_PAD_LEFT); }
    protected function validNumber(string $v): bool{return (bool)preg_match('/^\d+(?:\.\d{1,3})?$/',$v);}
    protected function recalculateStatus(int $id): void { $total=(float)($this->items->selectSum('quantity')->where('order_id',$id)->first()['quantity']??0);$del=(float)($this->deliveries->selectSum('quantity')->where('order_id',$id)->first()['quantity']??0);$status=$del<=0?'OPEN':($del+0.000001<$total?'PARTIAL':'COMPLETED');$this->orders->update($id,['status'=>$status,'updated_at'=>date('Y-m-d H:i:s')]); }
}
