<?php

namespace App\Models;

use CodeIgniter\Model;

class IncomingDocumentModel extends Model
{
    protected $table = 'incoming_documents';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'transaction_id', 'uploaded_by', 'original_filename', 'file_path',
        'document_type', 'ocr_status', 'ocr_data', 'verified', 'verified_by',
        'verified_at', 'created_at',
    ];
    protected $useTimestamps = false;
}
