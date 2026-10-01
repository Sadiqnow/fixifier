<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitQuotationRequest;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\JobEvidence;
use App\Models\Quotation;
use App\Models\Review;
use App\Services\Audit;
use App\Services\Journey;
use App\Services\DisputeResolution;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkflowController extends Controller
{
    public function quote(SubmitQuotationRequest $r, Booking $b): JsonResponse
    {
        $quote = DB::transaction(function () use ($r, $b) {
            $booking = $this->technicianBookingForUpdate($r, $b, BookingStatus::Requested);
            abort_unless($booking->request_accepted_at, 422, 'Accept the incoming request before quoting.');
            $data = $r->validated();
            $data['amount_minor'] = collect($data['items'])->sum(fn ($i) => $i['quantity'] * $i['unit_price_minor']);
            abort_unless($data['amount_minor'] >= 100 && $data['amount_minor'] <= 1000000000, 422, 'Invalid quotation total.');
            $data['version'] = (int) Quotation::where('booking_id', $booking->id)->max('version') + 1;
            $q = Quotation::create($data + ['booking_id' => $booking->id]);
            Journey::record($booking, 'quotation.submitted', 'Quotation version '.$q->version.' is ready for review.');
            $booking->update(['status' => BookingStatus::Quoted, 'lock_version' => $booking->lock_version + 1]);
            Audit::record('quotation.submitted', $q);

            return $q;
        }, 3);

        return response()->json(['data' => $quote], 201);
    }

    public function accept(Request $r, Booking $b): JsonResponse
    {
        $booking = DB::transaction(function () use ($r, $b) {
            $booking = $this->customerBookingForUpdate($r, $b, BookingStatus::Quoted);
            $quote = $booking->quotation()->lockForUpdate()->firstOrFail();
            $data = $r->validate(['quotation_id' => 'required|integer']);
            abort_unless((int) $data['quotation_id'] === $quote->id, 409, 'The quotation changed. Review the current version.');
            if ($quote->expires_at->lessThanOrEqualTo(now())) {
                $this->conflict($booking, 'quote_expired', 'This quotation has expired.');
            }
            if ($quote->accepted_at !== null) {
                $this->conflict($booking, 'invalid_transition', 'This quotation has already been accepted.');
            }
            $quote->update(['accepted_at' => now()]);
            $booking->update(['accepted_quotation_id' => $quote->id, 'status' => BookingStatus::Confirmed, 'lock_version' => $booking->lock_version + 1]);
            Journey::round($booking);
            Journey::record($booking, 'quotation.accepted', 'Customer accepted quotation version '.$quote->version.'.');
            Audit::record('quotation.accepted', $booking);

            return $booking;
        }, 3);

        return response()->json(['data' => $booking]);
    }

    public function start(Request $r, Booking $b): JsonResponse
    {
        $booking = DB::transaction(function () use ($r, $b) {
            $booking = $this->technicianBookingForUpdate($r, $b, BookingStatus::Confirmed);
            app(\App\Services\JourneyPayments::class)->requireFunding($booking);
            abort_unless($booking->evidence()->where('work_round', $booking->current_work_round)->where('type', 'before')->exists(), 422, 'Upload before evidence for the current work round before starting.');
            $booking->update(['status' => BookingStatus::InProgress, 'lock_version' => $booking->lock_version + 1]);
            Journey::updateRound($booking, ['started_at' => now()]);
            Journey::record($booking, 'work.started', 'Work has started.');
            Audit::record('booking.started', $booking, ['work_round' => $booking->current_work_round]);

            return $booking;
        }, 3);

        return response()->json(['data' => $booking]);
    }

    public function evidence(Request $r, Booking $b): JsonResponse
    {
        $path = null;
        try {
            // No transaction retry here: file writes are not repeatable database operations.
            $evidence = DB::transaction(function () use ($r, $b, &$path) {
                $booking = $this->technicianBookingForUpdate($r, $b);
                if (! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::InProgress], true)) {
                    $this->conflict($booking, 'invalid_transition', 'Evidence cannot be uploaded at this stage.');
                }
                // Existing jobs may already be in progress without a before record.
                // New starts always require before evidence; supplemental records retain capture times.
                $allowed = $booking->status === BookingStatus::Confirmed ? 'before' : 'before,after';
                $data = $r->validate(['type' => 'required|in:'.$allowed, 'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
                    'note' => 'nullable|string|max:2000', 'captured_at' => 'required|date|before_or_equal:now']);
                $phase = $data['type'];
                $file = $data['photo'];
                $path = $file->store("bookings/{$booking->id}/rounds/{$booking->current_work_round}", 'private');
                abort_unless($path, 500, 'Evidence could not be stored.');
                $e = JobEvidence::create(['booking_id' => $booking->id, 'technician_id' => $r->user()->id, 'work_round' => $booking->current_work_round,
                    'type' => $phase, 'storage_path' => $path, 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()), 'note' => $data['note'] ?? null, 'captured_at' => $data['captured_at']]);
                $booking->increment('lock_version');
                Audit::record('evidence.uploaded', $e, ['type' => $e->type, 'work_round' => $e->work_round]);

                return $e;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('private')->delete($path);
            }
            throw $e;
        }

        return response()->json(['data' => $evidence], 201);
    }

    public function submitEvidence(Request $r, Booking $b): JsonResponse
    {
        $booking = DB::transaction(function () use ($r, $b) {
            $booking = $this->technicianBookingForUpdate($r, $b, BookingStatus::InProgress);
            $data = $r->validate(['completion_notes' => 'nullable|string|min:10|max:5000']);
            $note = trim((string) ($data['completion_notes'] ?? '')) ?: 'Work completed.';
            $types = $booking->evidence()->where('work_round', $booking->current_work_round)->distinct()->pluck('type');
            abort_unless($types->contains('before') && $types->contains('after'), 422, 'Before and after evidence for this work round are both required.');
            $booking->update(['status' => BookingStatus::EvidenceSubmitted, 'lock_version' => $booking->lock_version + 1]);
            Journey::updateRound($booking, ['completion_notes' => $note, 'submitted_at' => now()]);
            Journey::record($booking, 'work.submitted', 'Completion is ready for customer review.');
            Audit::record('evidence.submitted', $booking, ['work_round' => $booking->current_work_round]);

            return $booking;
        }, 3);

        return response()->json(['data' => $booking]);
    }

    public function approve(Request $r, Booking $b): JsonResponse
    {
        $booking = DB::transaction(function () use ($r, $b) {
            $booking = $this->customerBookingForUpdate($r, $b, BookingStatus::EvidenceSubmitted);
            $booking->update(['status' => BookingStatus::Completed, 'settlement_status' => 'release_pending', 'release_approved_at' => now(), 'lock_version' => $booking->lock_version + 1]);
            $booking->payment()->where('status', 'authorized')->update(['status' => 'release_pending']);
            Journey::updateRound($booking, ['review' => 'approved', 'reviewed_at' => now()]);
            Journey::record($booking, 'work.approved', 'Customer approved completion. Settlement is pending provider confirmation.');
            Audit::record('booking.approved', $booking, ['work_round' => $booking->current_work_round]);

            return $booking;
        }, 3);

        return response()->json(['data' => $booking]);
    }

    public function dispute(Request $r, Booking $b): JsonResponse
    {
        $dispute = DB::transaction(function () use ($r, $b) {
            $booking = $this->customerBookingForUpdate($r, $b, BookingStatus::EvidenceSubmitted);
            $data = $r->validate(['reason' => 'required|string|max:120', 'details' => 'required|string|min:20|max:5000']);
            if ($booking->disputes()->where('work_round', $booking->current_work_round)->exists()) {
                $this->conflict($booking, 'dispute_already_opened', 'A dispute already exists for this work round.');
            }
            $booking->update(['status' => BookingStatus::Disputed, 'lock_version' => $booking->lock_version + 1]);
            $dispute = Dispute::create($data + ['booking_id' => $booking->id, 'opened_by' => $r->user()->id, 'status' => 'open', 'work_round' => $booking->current_work_round]);
            Journey::updateRound($booking, ['review' => 'disputed', 'reviewed_at' => now()]);
            Journey::record($booking, 'dispute.opened', $data['reason'], ['dispute_id' => $dispute->id]);
            Audit::record('dispute.opened', $dispute, ['booking_id' => $booking->id, 'work_round' => $booking->current_work_round]);

            return $dispute;
        }, 3);

        return response()->json(['data' => $dispute], 201);
    }

    public function resolve(Request $r, Dispute $d, DisputeResolution $resolution): JsonResponse
    {
        abort_unless($r->user()->role->value === 'admin' && $r->user()->is_active, 403);
        $data = $r->validate(['decision' => 'required|in:release,refund,rework', 'resolution' => 'required|string|min:20|max:5000']);

        return response()->json(['data' => $resolution->resolve($d, $r->user(), $data['decision'], $data['resolution'])]);
    }

    public function rating(Request $r, Booking $b): JsonResponse
    {
        $review = DB::transaction(function () use ($r, $b) {
            $booking = $this->customerBookingForUpdate($r, $b, BookingStatus::Completed);
            abort_unless($booking->technician_id && ! $booking->review()->exists(), 409, 'This booking already has a review or has no assigned technician.');
            $data = $r->validate(['stars' => 'required|integer|between:1,5', 'comment' => 'required|string|min:10|max:3000']);
            $review = Review::create($data + ['booking_id' => $booking->id, 'customer_id' => $r->user()->id, 'technician_id' => $booking->technician_id, 'status' => 'published', 'version' => 1]);
            Audit::record('review.submitted', $review, ['booking_id' => $booking->id]);

            return $review;
        }, 3);

        return response()->json(['data' => $review], 201);
    }

    private function customerBookingForUpdate(Request $r, Booking $bound, BookingStatus $expected): Booking
    {
        $b = Booking::whereKey($bound->id)->lockForUpdate()->firstOrFail();
        abort_unless($r->user()->role->value === 'customer' && $b->customer_id === $r->user()->id && $r->user()->is_active, 403, 'Only the booking customer may perform this action.');
        if ($b->status !== $expected) {
            $this->conflict($b, 'invalid_transition', 'The booking state no longer permits this action.');
        }
        // Quote acceptance and ratings are not tied to an evidence decision screen.
        if ($expected === BookingStatus::EvidenceSubmitted) {
            $this->checkRound($r, $b);
        }

        return $b;
    }

    private function technicianBookingForUpdate(Request $r, Booking $bound, ?BookingStatus $expected = null): Booking
    {
        $b = Booking::whereKey($bound->id)->lockForUpdate()->firstOrFail();
        abort_unless($r->user()->role->value === 'technician' && $b->technician_id === $r->user()->id && $r->user()->is_active, 403);
        if ($expected && $b->status !== $expected) {
            $this->conflict($b, 'invalid_transition', 'The booking state no longer permits this action.');
        }
        $this->checkRound($r, $b);

        return $b;
    }

    private function checkRound(Request $r, Booking $b): void
    {
        $data = $r->validate(['expected_work_round' => 'nullable|integer|min:1']);
        if (($b->current_work_round > 1 && ! isset($data['expected_work_round']))
            || (isset($data['expected_work_round']) && (int) $data['expected_work_round'] !== $b->current_work_round)) {
            $this->conflict($b, 'stale_work_round', 'The work round changed. Refresh the booking and review its current evidence.');
        }
    }

    private function conflict(Booking $b, string $code, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message, 'code' => $code, 'current_status' => $b->status->value, 'current_work_round' => $b->current_work_round,
        ],409));
    }
}
