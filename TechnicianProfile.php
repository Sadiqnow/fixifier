<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TechnicianProfile extends Model {
    protected $fillable=['user_id','trade','bio','years_experience','kyc_status','verified_at','business_name','city','state','latitude','longitude','is_available','average_rating','completed_jobs'];
    protected function casts():array{return ['verified_at'=>'datetime','is_available'=>'boolean','latitude'=>'decimal:7','longitude'=>'decimal:7'];}
    public function user():BelongsTo{return $this->belongsTo(User::class);}
}
