<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentTransaction extends Model {
    protected $fillable=['payment_id','type','provider_reference','amount_minor','status','payload'];
    protected function casts():array{return ['payload'=>'array'];}
}
