<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Quotation extends Model { protected $fillable=['booking_id','amount_minor','currency','scope','expires_at','accepted_at']; protected function casts():array{return ['expires_at'=>'datetime','accepted_at'=>'datetime'];} public function booking():BelongsTo{return $this->belongsTo(Booking::class);} }
