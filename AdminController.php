<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller; use App\Models\{AuditEvent,Booking,Dispute,User}; use Illuminate\Http\{JsonResponse,Request};
class AdminController extends Controller {
 public function users(Request $r):JsonResponse{$this->admin($r);$q=User::query();if($r->filled('role'))$q->where('role',$r->string('role'));if($r->filled('search')){$s='%'.$r->string('search').'%';$q->where(fn($x)=>$x->where('name','ilike',$s)->orWhere('email','ilike',$s)->orWhere('phone','ilike',$s));}return response()->json($q->latest()->paginate(30));}
 public function jobs(Request $r):JsonResponse{$this->admin($r);$q=Booking::with(['customer','technician','quotation','payment','dispute']);if($r->filled('status'))$q->where('status',$r->string('status'));return response()->json($q->latest()->paginate(30));}
 public function disputes(Request $r):JsonResponse{$this->admin($r);return response()->json(Dispute::with('booking')->latest()->paginate(30));}
 public function audits(Request $r):JsonResponse{$this->admin($r);return response()->json(AuditEvent::latest('id')->paginate(50));}
 private function admin(Request $r):void{abort_unless($r->user()->role->value==='admin',403);}
}
