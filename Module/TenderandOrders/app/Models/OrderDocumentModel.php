<?php
namespace App\Models;
use CodeIgniter\Model;
class OrderDocumentModel extends Model
{
    protected $table='order_documents'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['order_id','stage','file_name','file_path','mime_type','file_size','created_by','created_at'];
}
