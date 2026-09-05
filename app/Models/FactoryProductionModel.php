<?php
namespace App\Models;
use CodeIgniter\Model;
class FactoryProductionModel extends Model
{
    protected $table='factory_productions'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['production_date','product_id','category_id','item_name','operator_name','machine_name','quantity','transaction_id','created_by','created_at','updated_at'];
}
