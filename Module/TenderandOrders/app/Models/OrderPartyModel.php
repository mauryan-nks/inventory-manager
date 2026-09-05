<?php
namespace App\Models;
use CodeIgniter\Model;
class OrderPartyModel extends Model
{
    protected $table='order_parties'; protected $primaryKey='id'; protected $returnType='array';
    protected $allowedFields=['party_type','name','code','contact_person','phone','email','gstin','state','pincode','address','status','created_by','created_at','updated_at'];
}
