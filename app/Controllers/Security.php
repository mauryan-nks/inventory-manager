<?php

namespace App\Controllers;

use App\Models\IncomingDocumentModel;
use App\Models\InventoryTransactionModel;
use App\Models\ProductModel;
use App\Services\AuditService;
use App\Services\InventoryService;
use App\Services\AuthService;
use RuntimeException;

class Security extends BaseController
{
    protected IncomingDocumentModel $documents;
    protected ProductModel $products;
    protected InventoryService $inventory;

    public function __construct()
    {
        $this->documents = new IncomingDocumentModel();
        $this->products = new ProductModel();
        $this->inventory = new InventoryService();
    }

    public function index()
    {
        $auth = new AuthService();
        if (!$auth->can('security.scan') && !$auth->can('security.manual_entry') && !$auth->can('security.history')) {
            return redirect()->to('/dashboard')->with('error', 'Security access is not assigned to your account.');
        }
        $today = date('Y-m-d');
        $documents = $this->documents
            ->select('incoming_documents.*, users.name AS guard_name, inventory_transactions.transaction_no')
            ->join('users', 'users.id = incoming_documents.uploaded_by', 'left')
            ->join('inventory_transactions', 'inventory_transactions.id = incoming_documents.transaction_id', 'left')
            ->where('incoming_documents.created_at >=', $today . ' 00:00:00')
            ->orderBy('incoming_documents.id', 'DESC')
            ->findAll();

        return view('security/index', [
            'title' => 'Security Guard Dashboard',
            'documents' => $documents,
            'canScan' => (new AuthService())->can('security.scan'),
            'canManual' => (new AuthService())->can('security.manual_entry'),
        ]);
    }

    public function scan()
    {
        return view('security/scan', [
            'title' => 'Scan Incoming Document',
        ]);
    }

    public function upload()
    {
        $file = $this->request->getFile('document');
        if (!$file || !$file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'Please select a valid document or image.');
        }

        $mime = strtolower($file->getMimeType());
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed, true)) {
            return redirect()->back()->with('error', 'Only PDF, JPG, PNG, and WEBP files are allowed.');
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return redirect()->back()->with('error', 'The document must be 10 MB or smaller.');
        }

        $dir = WRITEPATH . 'uploads/incoming';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $stored = $file->getRandomName();
        $file->move($dir, $stored);

        $ocr = $this->runOcr($dir . DIRECTORY_SEPARATOR . $stored, $mime);
        $data = [
            'reference_no' => $ocr['reference_no'] ?? '',
            'party_name' => $ocr['party_name'] ?? '',
            'document_date' => $ocr['document_date'] ?? '',
            'vehicle_no' => $ocr['vehicle_no'] ?? '',
            'raw_text' => $ocr['raw_text'] ?? '',
        ];

        $id = $this->documents->insert([
            'uploaded_by' => (int) service('session')->get('user_id'),
            'original_filename' => $file->getClientName(),
            'file_path' => 'uploads/incoming/' . $stored,
            'document_type' => 'incoming',
            'ocr_status' => $ocr['status'],
            'ocr_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'verified' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        (new AuditService())->record('UPLOAD_DOCUMENT', 'security', (int)$id, 'Incoming document uploaded.');

        return redirect()->to('/security/scan/' . $id)->with('success', 'Document uploaded. Review the extracted information before confirming.');
    }

    public function review(int $id)
    {
        $document = $this->documents->find($id);
        if (!$document || (int)$document['verified'] === 1) {
            return redirect()->to('/security')->with('error', 'Incoming document not found or already registered.');
        }

        $data = json_decode($document['ocr_data'] ?? '{}', true) ?: [];
        return view('security/review', [
            'title' => 'Verify Incoming Document',
            'document' => $document,
            'extracted' => $data,
            'products' => $this->products->where('status', 1)->orderBy('name')->findAll(),
            'productMap' => $this->productMap(),
            'variantMap' => $this->variantMap(),
        ]);
    }

    public function confirm(int $id)
    {
        $document = $this->documents->find($id);
        if (!$document || (int)$document['verified'] === 1) {
            return redirect()->to('/security')->with('error', 'Incoming document not found or already registered.');
        }

        $reference = trim((string)$this->request->getPost('reference_no'));
        if ($reference !== '') {
            $existing = (new InventoryTransactionModel())
                ->where('type', 'IN')->where('reference_no', $reference)->first();
            if ($existing) {
                return redirect()->back()->withInput()->with('error', 'This reference number is already registered as an IN transaction.');
            }
        }

        $productIds = $this->request->getPost('product_id') ?? [];
        $quantities = $this->request->getPost('quantity') ?? [];
        $variantIds = $this->request->getPost('variant_id') ?? [];
        $items = [];
        if (is_array($productIds) && is_array($quantities)) {
            foreach ($productIds as $i => $productId) {
                $items[] = [
                    'product_id' => (int)$productId,
                    'quantity' => (float)($quantities[$i] ?? 0),
                    'variant_id' => (int)($variantIds[$i] ?? 0),
                ];
            }
        }

        try {
            $transactionId = $this->inventory->createTransaction('IN', $items, (int)service('session')->get('user_id'), [
                'reference_no' => $reference ?: null,
                'party_name' => trim((string)$this->request->getPost('party_name')) ?: null,
                'vehicle_no' => trim((string)$this->request->getPost('vehicle_no')) ?: null,
                'remarks' => trim((string)$this->request->getPost('remarks')) ?: null,
            ]);

            $corrected = json_decode($document['ocr_data'] ?? '{}', true) ?: [];
            $corrected['reference_no'] = $reference;
            $corrected['party_name'] = trim((string)$this->request->getPost('party_name'));
            $corrected['vehicle_no'] = trim((string)$this->request->getPost('vehicle_no'));
            $corrected['document_date'] = trim((string)$this->request->getPost('document_date'));
            $corrected['verified_items'] = $items;

            $this->documents->update($id, [
                'transaction_id' => $transactionId,
                'ocr_data' => json_encode($corrected, JSON_UNESCAPED_UNICODE),
                'verified' => 1,
                'verified_by' => (int)service('session')->get('user_id'),
                'verified_at' => date('Y-m-d H:i:s'),
            ]);

            (new AuditService())->record('CONFIRM_INCOMING', 'security', $id, 'Scanned incoming document verified and converted to inventory IN.', null, ['transaction_id' => $transactionId]);
            return redirect()->to('/security')->with('success', 'Incoming document verified and inventory IN created.');
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function manual()
    {
        return view('security/manual', [
            'title' => 'Manual Incoming Entry',
            'products' => $this->products->where('status', 1)->orderBy('name')->findAll(),
            'productMap' => $this->productMap(),
            'variantMap' => $this->variantMap(),
        ]);
    }

    public function manualStore()
    {
        $reference = trim((string)$this->request->getPost('reference_no'));
        if ($reference !== '') {
            $existing = (new InventoryTransactionModel())->where('type', 'IN')->where('reference_no', $reference)->first();
            if ($existing) {
                return redirect()->back()->withInput()->with('error', 'This reference number is already registered.');
            }
        }

        $productIds = $this->request->getPost('product_id') ?? [];
        $quantities = $this->request->getPost('quantity') ?? [];
        $variantIds = $this->request->getPost('variant_id') ?? [];
        $items = [];
        if (is_array($productIds) && is_array($quantities)) {
            foreach ($productIds as $i => $productId) {
                $items[] = [
                    'product_id' => (int)$productId,
                    'quantity' => (float)($quantities[$i] ?? 0),
                    'variant_id' => (int)($variantIds[$i] ?? 0),
                ];
            }
        }

        try {
            $transactionId = $this->inventory->createTransaction('IN', $items, (int)service('session')->get('user_id'), [
                'reference_no' => $reference ?: null,
                'party_name' => trim((string)$this->request->getPost('party_name')) ?: null,
                'vehicle_no' => trim((string)$this->request->getPost('vehicle_no')) ?: null,
                'remarks' => trim((string)$this->request->getPost('remarks')) ?: null,
            ]);

            $this->documents->insert([
                'transaction_id' => $transactionId,
                'uploaded_by' => (int)service('session')->get('user_id'),
                'document_type' => 'manual',
                'ocr_status' => 'not_applicable',
                'verified' => 1,
                'verified_by' => (int)service('session')->get('user_id'),
                'verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            (new AuditService())->record('MANUAL_INCOMING', 'security', $transactionId, 'Manual incoming entry created.');
            return redirect()->to('/security')->with('success', 'Manual incoming entry created successfully.');
        } catch (RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function download(int $id)
    {
        $doc = $this->documents->find($id);
        if (!$doc || empty($doc['file_path'])) {
            return redirect()->to('/security')->with('error', 'Document file not found.');
        }
        $full = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, ltrim($doc['file_path'], '/'));
        if (!is_file($full)) {
            return redirect()->to('/security')->with('error', 'Document file is unavailable.');
        }
        return $this->response->download($full, null)->setFileName($doc['original_filename'] ?: basename($full));
    }

    public function history()
    {
        if (!(new AuthService())->can('security.history')) {
            return redirect()->to('/dashboard')->with('error', 'Security history access is not assigned to your account.');
        }
        $q = trim((string)$this->request->getGet('q'));
        $builder = $this->documents->select('incoming_documents.*, users.name AS guard_name, inventory_transactions.transaction_no, inventory_transactions.reference_no, inventory_transactions.party_name')->join('users', 'users.id = incoming_documents.uploaded_by', 'left')->join('inventory_transactions', 'inventory_transactions.id = incoming_documents.transaction_id', 'left');
        if ($q !== '') { $builder->groupStart()->like('incoming_documents.original_filename',$q)->orLike('inventory_transactions.reference_no',$q)->orLike('inventory_transactions.party_name',$q)->orLike('users.name',$q)->groupEnd(); }
        $documents = $builder->orderBy('incoming_documents.id', 'DESC')->findAll();
        return view('security/history', ['title' => 'Incoming History', 'documents' => $documents, 'q'=>$q]);
    }

    protected function runOcr(string $path, string $mime): array
    {
        $raw = '';
        $status = 'unavailable';
        $binary = trim((string) shell_exec('command -v tesseract 2>/dev/null'));

        if ($binary !== '') {
            $input = $path;
            $temporaryPng = null;
            if ($mime === 'application/pdf') {
                $pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
                if ($pdftotext !== '') {
                    $tmpBase = tempnam(sys_get_temp_dir(), 'invocr_');
                    $temporaryTxt = $tmpBase . '.txt';
                    @shell_exec($pdftotext . ' -layout ' . escapeshellarg($path) . ' ' . escapeshellarg($temporaryTxt) . ' 2>/dev/null');
                    if (is_file($temporaryTxt)) {
                        $raw = (string) file_get_contents($temporaryTxt);
                        @unlink($temporaryTxt);
                    }
                    @unlink($tmpBase);
                }
            } else {
                $base = tempnam(sys_get_temp_dir(), 'invocr_');
                @unlink($base);
                $outBase = $base;
                @shell_exec($binary . ' ' . escapeshellarg($input) . ' ' . escapeshellarg($outBase) . ' 2>/dev/null');
                $txt = $outBase . '.txt';
                if (is_file($txt)) {
                    $raw = (string) file_get_contents($txt);
                    @unlink($txt);
                }
            }
            if (trim($raw) !== '') {
                $status = 'completed';
            }
        }

        return [
            'status' => $status,
            'raw_text' => $raw,
            'reference_no' => $this->extract($raw, '/(?:invoice|bill|reference|ref(?:erence)?)[\s:#-]*([A-Z0-9][A-Z0-9\/-]{2,})/i'),
            'vehicle_no' => $this->extract($raw, '/(?:vehicle|truck|registration|reg\.?)[\s:#-]*([A-Z]{1,3}[ -]?[0-9]{1,4}[ -]?[A-Z]{0,3}[ -]?[0-9]{1,4})/i'),
            'party_name' => $this->extract($raw, '/(?:supplier|sender|vendor|from)[\s:#-]*([^\r\n]+)/i'),
            'document_date' => $this->extract($raw, '/\b(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})\b/'),
        ];
    }

    protected function extract(string $text, string $pattern): string
    {
        if (preg_match($pattern, $text, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    protected function productMap(): array
    {
        $map=[];
        foreach($this->products->where('status',1)->findAll() as $row){
            $map[(int)$row['id']]=['measurement_type'=>strtoupper((string)($row['measurement_type']??'STANDARD')),'unit'=>(string)($row['unit']??'')];
        }
        return $map;
    }

    protected function variantMap(): array
    {
        $map=[];
        foreach(db_connect()->table('product_variants')->where('status',1)->orderBy('variant_name')->get()->getResultArray() as $row){
            $map[(int)$row['id']]=$row;
        }
        return $map;
    }



}
