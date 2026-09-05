<?php
namespace App\Models;
use CodeIgniter\Model;
class OrderModel extends Model
{
    protected $table='orders'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['order_no','party_id','order_date','delivery_start_date','delivery_end_date','status','notes','created_by','created_at','updated_at'];
}
