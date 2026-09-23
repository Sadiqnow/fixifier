<?php
namespace App\Http\Controllers\Api\V1;
use App\Enums\BookingStatus; use App\Http\Controllers\Controller; use App\Models\{Booking,Review,TechnicianProfile}; use App\Services\Audit; use Illuminate\Http\{JsonResponse,Request};
class ReviewController extends Controller {
 public function store(Request $r,Booking $booking):JsonResponse{abort_unless($booking->customer_id===$r->user()->id&&$booking->status===BookingStatus::Completed&&$booking->technician_id,403);$d=$r->validate(['rating'=>'required|integer|between:1,5','comment'=>'nullable|string|max:2000']);$review=Review::create($d+['booking_id'=>$booking->id,'customer_id'=>$r->user()->id,'technician_id'=>$booking->technician_id]);$avg=Review::where('technician_id',$booking->technician_id)->where('is_visible',true)->avg('rating');TechnicianProfile::where('user_id',$booking->technician_id)->update(['average_rating'=>$avg]);Audit::record('review.created',$review);return response()->json(['data'=>$review],201);}
 public function technician(int $technician):JsonResponse{return response()->json(Review::where('technician_id',$technician)->where('is_visible',true)->latest()->paginate(20));}
}
