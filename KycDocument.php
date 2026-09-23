<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class KycDocument extends Model {
    protected $fillable=['technician_id','type','storage_path','status','rejection_reason','reviewed_by','reviewed_at'];
    protected $hidden=['storage_path'];
    protected function casts():array{return ['reviewed_at'=>'datetime'];}
}
