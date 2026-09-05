<?php
namespace App\Models;
use CodeIgniter\Model;
class OrderDeliveryModel extends Model
{
    protected $table='order_deliveries'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['order_id','order_item_id','delivery_date','quantity','reference_no','remarks','created_by','created_at'];
}
