<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditEvent extends Model { public $timestamps=false; protected $fillable=['actor_id','action','subject_type','subject_id','metadata','ip_address','created_at']; protected function casts():array{return ['metadata'=>'array','created_at'=>'datetime'];} }
