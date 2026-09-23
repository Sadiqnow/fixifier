<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller; use Illuminate\Http\{JsonResponse,Request};
class NotificationController extends Controller {
 public function index(Request $r):JsonResponse{return response()->json($r->user()->notifications()->latest()->paginate(30));}
 public function read(Request $r,string $id):JsonResponse{$n=$r->user()->notifications()->findOrFail($id);$n->markAsRead();return response()->json(['data'=>$n]);}
 public function readAll(Request $r):JsonResponse{$r->user()->unreadNotifications()->update(['read_at'=>now()]);return response()->json(['message'=>'All notifications marked as read.']);}
}
