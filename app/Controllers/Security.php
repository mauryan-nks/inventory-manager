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
            'ocrReady' => $this->ocrExecutionReady(),
            'ocrServerBinary' => $this->findBinary(['tesseract','/usr/bin/tesseract','/usr/local/bin/tesseract']) !== null,
            'ocrLanguage' => app_locale() === 'hi' ? 'hin' : 'eng',
            'pdfToolsReady' => $this->findBinary(['pdftoppm','/usr/bin/pdftoppm','/usr/local/bin/pdftoppm']) !== null,
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

        
        // Prefer browser OCR text when available. This provides a reliable fallback on hosts
        // where PHP-FPM cannot execute Tesseract, while the original file is still stored server-side.
        $clientOcrText = trim((string) $this->request->getPost('client_ocr_text'));
        $clientOcrLanguage = trim((string) $this->request->getPost('client_ocr_language'));
        $ocr = $this->runOcr($dir . DIRECTORY_SEPARATOR . $stored, $mime);
        if ($clientOcrText !== '' && strlen($clientOcrText) >= 5 && $mime !== 'application/pdf') {
            $ocr['status'] = 'completed';
            $ocr['raw_text'] = $clientOcrText;
            $ocr['diagnostic'] = 'OCR extracted in the browser' . ($clientOcrLanguage !== '' ? ' (' . $clientOcrLanguage . ')' : '') . '.';
            $ocr['reference_no'] = $this->extract($clientOcrText, '/(?:invoice|bill|reference|ref(?:erence)?|inv\.?)[\s:#-]*([A-Z0-9][A-Z0-9\/.-]{2,})/i');
            $ocr['vehicle_no'] = $this->extract($clientOcrText, '/(?:vehicle|truck|registration|reg\.?)[\s:#-]*([A-Z]{1,3}[ -]?[0-9]{1,4}[ -]?[A-Z]{0,3}[ -]?[0-9]{1,4})/i');
            $ocr['party_name'] = $this->extract($clientOcrText, '/(?:supplier|sender|vendor|from|party details?)[\s:#-]*([^\r\n]+)/i');
            $ocr['document_date'] = $this->extract($clientOcrText, '/\b(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}|\d{1,2}[- ](?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*[- ]\d{2,4})\b/i');
        }
        $data = [
            'reference_no' => $ocr['reference_no'] ?? '',
            'party_name' => $ocr['party_name'] ?? '',
            'document_date' => $ocr['document_date'] ?? '',
            'vehicle_no' => $ocr['vehicle_no'] ?? '',
            'raw_text' => $ocr['raw_text'] ?? '',
            'diagnostic' => $ocr['diagnostic'] ?? '',
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
        foreach ((array)$quantities as $q) {
            $q = trim((string)$q);
            if ($q === '' || !ctype_digit($q) || (int)$q < 1) {
                return redirect()->back()->withInput()->with('error', 'Quantity must be a whole number greater than 0.');
            }
        }
        if (is_array($productIds) && is_array($quantities)) {
            foreach ($productIds as $i => $productId) {
                $items[] = [
                    'product_id' => (int)$productId,
                    'quantity' => (int)($quantities[$i] ?? 0),
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
        foreach ((array)$quantities as $q) {
            $q = trim((string)$q);
            if ($q === '' || !ctype_digit($q) || (int)$q < 1) {
                return redirect()->back()->withInput()->with('error', 'Quantity must be a whole number greater than 0.');
            }
        }
        if (is_array($productIds) && is_array($quantities)) {
            foreach ($productIds as $i => $productId) {
                $items[] = [
                    'product_id' => (int)$productId,
                    'quantity' => (int)($quantities[$i] ?? 0),
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
        $diagnostic = '';
        $tesseract = $this->findBinary(['tesseract', '/usr/bin/tesseract', '/usr/local/bin/tesseract', '/opt/homebrew/bin/tesseract']);
        $pdftotext = $this->findBinary(['pdftotext', '/usr/bin/pdftotext', '/usr/local/bin/pdftotext']);
        $pdftoppm = $this->findBinary(['pdftoppm', '/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm']);

        if ($mime === 'application/pdf' && $pdftotext) {
            $txt = tempnam(sys_get_temp_dir(), 'invocr_txt_');
            $this->runCommand($pdftotext, ['-layout', $path, $txt], $diagnostic);
            if (is_file($txt)) {
                $raw = trim((string) file_get_contents($txt));
                @unlink($txt);
            }
        }

        // Scanned/image-only PDFs need to be rendered first, then passed through Tesseract.
        if ($mime === 'application/pdf' && trim($raw) === '' && $tesseract && $pdftoppm) {
            $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'invocr_pdf_' . bin2hex(random_bytes(5));
            $this->runCommand($pdftoppm, ['-png', '-r', '200', '-f', '1', '-l', '20', $path, $base], $diagnostic);
            $pages = glob($base . '-*.png') ?: [];
            natsort($pages);
            foreach ($pages as $png) {
                $pageText = $this->tesseractRead($tesseract, $png, $diagnostic);
                if ($pageText !== '') $raw .= ($raw === '' ? '' : "\n\n") . $pageText;
                @unlink($png);
            }
        }

        if ($mime !== 'application/pdf' && $tesseract) {
            $raw = $this->tesseractRead($tesseract, $path, $diagnostic);
        }

        if (trim($raw) !== '') {
            $status = 'completed';
            $diagnostic = '';
        } elseif (!$tesseract && $mime !== 'application/pdf') {
            $diagnostic = 'Tesseract OCR is not installed or is not available to PHP. Install tesseract-ocr and restart/reload PHP-FPM.';
        } elseif ($mime === 'application/pdf' && !$pdftotext && !$pdftoppm) {
            $diagnostic = 'PDF tools are not available to PHP. Install poppler-utils (pdftotext + pdftoppm).';
        } elseif ($mime === 'application/pdf' && !$tesseract && $pdftotext) {
            $diagnostic = 'This PDF has no readable text layer. Install tesseract-ocr + poppler-utils to OCR scanned PDFs.';
        } elseif ($diagnostic === '') {
            $diagnostic = 'OCR ran but could not extract readable text. Try a clearer scan/photo.';
        }

        return [
            'status' => $status,
            'raw_text' => $raw,
            'diagnostic' => $diagnostic,
            'reference_no' => $this->extract($raw, '/(?:invoice|bill|reference|ref(?:erence)?)[\s:#-]*([A-Z0-9][A-Z0-9\/-]{2,})/i'),
            'vehicle_no' => $this->extract($raw, '/(?:vehicle|truck|registration|reg\.?)[\s:#-]*([A-Z]{1,3}[ -]?[0-9]{1,4}[ -]?[A-Z]{0,3}[ -]?[0-9]{1,4})/i'),
            'party_name' => $this->extract($raw, '/(?:supplier|sender|vendor|from)[\s:#-]*([^\r\n]+)/i'),
            'document_date' => $this->extract($raw, '/\b(\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4})\b/'),
        ];
    }

    protected function ocrExecutionReady(): bool
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $runner = function_exists('proc_open') && !in_array('proc_open', $disabled, true)
            || function_exists('exec') && !in_array('exec', $disabled, true)
            || function_exists('shell_exec') && !in_array('shell_exec', $disabled, true)
            || function_exists('popen') && !in_array('popen', $disabled, true);
        return $runner && $this->findBinary(['tesseract','/usr/bin/tesseract','/usr/local/bin/tesseract']) !== null;
    }

    protected function findBinary(array $candidates): ?string
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $canShell = function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);
        if ($canShell) {
            foreach ($candidates as $candidate) {
                if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_executable($candidate)) return $candidate;
                $found = trim((string) @shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null'));
                if ($found !== '' && is_executable($found)) return $found;
            }
        }
        foreach ($candidates as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_executable($candidate)) return $candidate;
        }
        return null;
    }

    protected function runCommand(string $binary, array $args, ?string &$diagnostic = null): string
    {
        $cmd = escapeshellarg($binary);
        foreach ($args as $arg) $cmd .= ' ' . escapeshellarg((string) $arg);
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

        if (function_exists('proc_open') && !in_array('proc_open', $disabled, true)) {
            $pipes = [];
            $process = @proc_open($cmd . ' 2>&1', [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
            if (is_resource($process)) {
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]);
                $code = proc_close($process);
                if ($code !== 0 && trim((string)$stderr) !== '') $diagnostic = trim((string)$stderr);
                return trim((string)$stdout);
            }
        }
        if (function_exists('exec') && !in_array('exec', $disabled, true)) {
            $lines = [];
            $code = 0;
            @exec($cmd . ' 2>&1', $lines, $code);
            $output = trim(implode("\n", $lines));
            if ($code !== 0 && $output !== '') $diagnostic = $output;
            return $output;
        }
        if (function_exists('shell_exec') && !in_array('shell_exec', $disabled, true)) {
            return trim((string) @shell_exec($cmd . ' 2>&1'));
        }
        if (function_exists('popen') && !in_array('popen', $disabled, true)) {
            $handle = @popen($cmd . ' 2>&1', 'r');
            if (is_resource($handle)) {
                $output = stream_get_contents($handle);
                pclose($handle);
                return trim((string)$output);
            }
        }
        $diagnostic = 'PHP-FPM has disabled all supported process functions (proc_open, exec, shell_exec, popen). Browser OCR will be used for image uploads.';
        return '';
    }

    protected function tesseractRead(string $binary, string $input, ?string &$diagnostic = null): string
    {
        $langs = trim($this->runCommand($binary, ['--list-langs'], $diagnostic));
        $locale = app_locale();
        $language = $locale === 'hi' ? 'hin' : 'eng';
        if ($locale === 'hinglish') $language = 'eng';
        if ($locale === 'hi' && !preg_match('/(?:^|\n)hin(?:\s|$)/i', $langs)) {
            $language = 'eng';
            $diagnostic = 'Hindi OCR language pack (hin) is not installed. Browser OCR will use English until it is installed.';
        }
        $outDir = WRITEPATH . 'uploads/ocr_tmp';
        if (!is_dir($outDir)) @mkdir($outDir, 0750, true);
        $outBase = $outDir . DIRECTORY_SEPARATOR . 'ocr_' . bin2hex(random_bytes(7));
        $this->runCommand($binary, [$input, $outBase, '-l', $language, '--psm', '6'], $diagnostic);
        $txt = $outBase . '.txt';
        $raw = is_file($txt) ? (string) file_get_contents($txt) : '';
        if (is_file($txt)) @unlink($txt);
        return trim($raw);
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
