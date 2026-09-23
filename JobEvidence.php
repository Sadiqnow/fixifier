<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class JobEvidence extends Model { protected $table='job_evidence'; protected $fillable=['booking_id','technician_id','type','storage_path','mime_type','size_bytes','sha256','note','captured_at']; protected $hidden=['storage_path']; protected function casts():array{return ['captured_at'=>'datetime'];} public function booking():BelongsTo{return $this->belongsTo(Booking::class);} }
