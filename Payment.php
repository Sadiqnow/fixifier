<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Payment extends Model { protected $fillable=['booking_id','provider','provider_reference','amount_minor','currency','status','authorized_at','released_at','refunded_at']; protected function casts():array{return ['authorized_at'=>'datetime','released_at'=>'datetime','refunded_at'=>'datetime'];} public function booking():BelongsTo{return $this->belongsTo(Booking::class);} }
