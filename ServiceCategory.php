<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class ServiceCategory extends Model {
    protected $fillable=['name','slug','description','icon','is_active','sort_order'];
    protected function casts():array{return ['is_active'=>'boolean'];}
    public function technicians():BelongsToMany{return $this->belongsToMany(User::class,'category_technician','service_category_id','technician_id');}
}
