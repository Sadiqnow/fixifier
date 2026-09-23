<?php
namespace App\Services;
use App\Models\AuditEvent; use Illuminate\Database\Eloquent\Model;
final class Audit { public static function record(string $action,Model $subject,array $metadata=[]):void { AuditEvent::create(['actor_id'=>auth()->id(),'action'=>$action,'subject_type'=>$subject::class,'subject_id'=>$subject->getKey(),'metadata'=>$metadata,'ip_address'=>request()->ip(),'created_at'=>now()]); } }
