<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Dispute extends Model { protected $fillable=['booking_id','opened_by','reason','details','status','resolution','resolved_by','resolved_at']; protected function casts():array{return ['resolved_at'=>'datetime'];} public function booking():BelongsTo{return $this->belongsTo(Booking::class);} }
