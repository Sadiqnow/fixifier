<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Review extends Model {
    protected $fillable=['booking_id','customer_id','technician_id','rating','comment','is_visible'];
    protected function casts():array{return ['is_visible'=>'boolean'];}
    public function booking():BelongsTo{return $this->belongsTo(Booking::class);}
}
