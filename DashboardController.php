<?php
namespace App\Http\Controllers\Api\V1;
use App\Enums\UserRole; use App\Http\Controllers\Controller; use App\Models\{Booking,Dispute,Payment,Review,User}; use Illuminate\Http\{JsonResponse,Request};
class DashboardController extends Controller {
 public function show(Request $r):JsonResponse{$u=$r->user();return match($u->role){
  UserRole::Customer=>$this->customer($u->id),UserRole::Technician=>$this->technician($u->id),UserRole::Admin=>$this->admin()};}
 private function customer(int $id):JsonResponse{$q=Booking::where('customer_id',$id);return response()->json(['data'=>['jobs'=>['total'=>(clone $q)->count(),'active'=>(clone $q)->whereNotIn('status',['completed','cancelled'])->count(),'completed'=>(clone $q)->where('status','completed')->count()],'recent_jobs'=>(clone $q)->latest()->limit(5)->with(['technician','quotation','payment'])->get()]]);}
 private function technician(int $id):JsonResponse{$q=Booking::where('technician_id',$id);return response()->json(['data'=>['jobs'=>['assigned'=>(clone $q)->count(),'active'=>(clone $q)->whereIn('status',['confirmed','in_progress','evidence_submitted'])->count(),'completed'=>(clone $q)->where('status','completed')->count()],'earnings_minor'=>Payment::whereHas('booking',fn($x)=>$x->where('technician_id',$id))->where('status','released')->sum('amount_minor'),'recent_jobs'=>(clone $q)->latest()->limit(5)->with(['customer','quotation','payment'])->get()]]);}
 private function admin():JsonResponse{return response()->json(['data'=>['users'=>User::selectRaw('role,count(*) total')->groupBy('role')->pluck('total','role'),'jobs'=>Booking::selectRaw('status,count(*) total')->groupBy('status')->pluck('total','status'),'open_disputes'=>Dispute::where('status','open')->count(),'escrow_minor'=>Payment::where('status','authorized')->sum('amount_minor'),'released_minor'=>Payment::where('status','released')->sum('amount_minor'),'reviews'=>['count'=>Review::count(),'average'=>round((float)Review::avg('rating'),2)]]]);}
}
