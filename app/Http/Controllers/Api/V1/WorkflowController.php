<?php
namespace App\Http\Controllers\Api\V1;
use App\Enums\BookingStatus; use App\Http\Controllers\Controller; use App\Http\Requests\SubmitQuotationRequest; use App\Models\{Booking,Dispute,JobEvidence,Payment,Quotation}; use App\Services\Audit; use Illuminate\Http\{JsonResponse,Request}; use Illuminate\Support\Facades\{DB,Storage};
class WorkflowController extends Controller {
 public function quote(SubmitQuotationRequest $r,Booking $b):JsonResponse{abort_unless($b->technician_id===$r->user()->id&&$b->status===BookingStatus::Requested,409);$q=Quotation::updateOrCreate(['booking_id'=>$b->id],$r->validated());$b->update(['status'=>BookingStatus::Quoted]);Audit::record('quotation.submitted',$q);return response()->json(['data'=>$q],201);}
 public function accept(Request $r, Booking $b): JsonResponse
 {
     $booking = DB::transaction(function () use ($r, $b) {
         $booking = $this->customerBookingForUpdate($r, $b, BookingStatus::Quoted);
         $quote = $booking->quotation()->lockForUpdate()->firstOrFail();
         if ($quote->expires_at->lessThanOrEqualTo(now())) {
             $this->conflict($booking, 'quote_expired', 'This quotation has expired.');
         }
         if ($quote->accepted_at !== null) {
             $this->conflict($booking, 'invalid_transition', 'This quotation has already been accepted.');
         }
         $quote->update(['accepted_at' => now()]);
         $booking->update(['status' => BookingStatus::Confirmed]);
         Audit::record('quotation.accepted', $booking);
         return $booking;
     });
     return response()->json(['data' => $booking]);
 }
 public function start(Request $r,Booking $b):JsonResponse{abort_unless($b->technician_id===$r->user()->id&&$b->status===BookingStatus::Confirmed,409);$b->update(['status'=>BookingStatus::InProgress]);Audit::record('booking.started',$b);return response()->json(['data'=>$b]);}
 public function evidence(Request $r,Booking $b):JsonResponse{abort_unless($b->technician_id===$r->user()->id&&$b->status===BookingStatus::InProgress,409);$data=$r->validate(['type'=>'required|in:before,after','photo'=>'required|image|mimes:jpg,jpeg,png,webp|max:10240','note'=>'nullable|string|max:2000','captured_at'=>'required|date|before_or_equal:now']);$file=$data['photo'];$path=$file->store("bookings/{$b->id}/evidence",'private');$e=JobEvidence::create(['booking_id'=>$b->id,'technician_id'=>$r->user()->id,'type'=>$data['type'],'storage_path'=>$path,'mime_type'=>$file->getMimeType(),'size_bytes'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getRealPath()),'note'=>$data['note']??null,'captured_at'=>$data['captured_at']]);Audit::record('evidence.uploaded',$e,['type'=>$e->type]);return response()->json(['data'=>$e],201);}
 public function submitEvidence(Request $r,Booking $b):JsonResponse{abort_unless($b->technician_id===$r->user()->id&&$b->status===BookingStatus::InProgress,409);$types=$b->evidence()->distinct()->pluck('type');abort_unless($types->contains('before')&&$types->contains('after'),422,'Before and after evidence are both required.');$b->update(['status'=>BookingStatus::EvidenceSubmitted]);Audit::record('evidence.submitted',$b);return response()->json(['data'=>$b]);}
 public function approve(Request $r, Booking $b): JsonResponse
 {
     $booking = DB::transaction(function () use ($r, $b) {
         $booking = $this->customerBookingForUpdate($r, $b, BookingStatus::EvidenceSubmitted);
         $booking->update(['status' => BookingStatus::Completed]);
         // Existing demo ledger behavior; this does not initiate a provider transfer.
         $booking->payment()->where('status', 'authorized')->update(['status' => 'released', 'released_at' => now()]);
         Audit::record('booking.approved', $booking);
         return $booking;
     });
     return response()->json(['data' => $booking]);
 }

 public function dispute(Request $r, Booking $b): JsonResponse
 {
     $dispute = DB::transaction(function () use ($r, $b) {
         $booking = $this->customerBookingForUpdate($r, $b, BookingStatus::EvidenceSubmitted);
         $data = $r->validate(['reason' => 'required|string|max:120', 'details' => 'required|string|min:20|max:5000']);
         // Until round-aware schema lands, preserve the existing dispute history.
         if ($booking->dispute()->exists()) {
             $this->conflict($booking, 'dispute_round_not_supported', 'A dispute already exists for this booking.');
         }
         $booking->update(['status' => BookingStatus::Disputed]);
         $dispute = Dispute::create($data + ['booking_id' => $booking->id, 'opened_by' => $r->user()->id, 'status' => 'open']);
         Audit::record('dispute.opened', $dispute);
         return $dispute;
     });
     return response()->json(['data' => $dispute], 201);
 }

 private function customerBookingForUpdate(Request $r, Booking $boundBooking, BookingStatus $expected): Booking
 {
     // Route binding may predate a competing decision: reload under the transaction lock.
     $booking = Booking::query()->whereKey($boundBooking->getKey())->lockForUpdate()->firstOrFail();
     abort_unless($r->user()->role->value === 'customer' && $booking->customer_id === $r->user()->id, 403, 'Only the booking customer may perform this action.');
     if ($booking->status !== $expected) {
         $this->conflict($booking, 'invalid_transition', 'The booking state no longer permits this action.');
     }
     return $booking;
 }

 private function conflict(Booking $booking, string $code, string $message): never
 {
     throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
         'message' => $message,
         'code' => $code,
         'current_status' => $booking->status->value,
     ], 409));
 }
 public function resolve(Request $r,Dispute $d):JsonResponse{abort_unless($r->user()->role->value==='admin'&&$d->status==='open',403);$data=$r->validate(['decision'=>'required|in:release,refund,rework','resolution'=>'required|string|min:20|max:5000']);DB::transaction(function()use($d,$r,$data){$d->update(['status'=>'resolved','resolution'=>$data['resolution'],'resolved_by'=>$r->user()->id,'resolved_at'=>now()]);$status=$data['decision']==='release'?BookingStatus::Completed:($data['decision']==='rework'?BookingStatus::InProgress:BookingStatus::Cancelled);$d->booking()->update(['status'=>$status]);if($data['decision']==='release')$d->booking->payment()->update(['status'=>'released','released_at'=>now()]);if($data['decision']==='refund')$d->booking->payment()->update(['status'=>'refunded','refunded_at'=>now()]);});Audit::record('dispute.resolved',$d,['decision'=>$data['decision']]);return response()->json(['data'=>$d->fresh()]);}
}
