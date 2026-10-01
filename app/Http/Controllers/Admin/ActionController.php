<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminActionRequest;
use App\Models\Booking;
use App\Models\EvidenceFlag;
use App\Models\JobEvidence;
use App\Models\KycDocument;
use App\Models\MarketplaceSetting;
use App\Models\Review;
use App\Models\TechnicianDecision;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\Audit;
use App\Services\DisputeResolution;
use App\Services\EligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ActionController extends Controller
{
    private function version(int $current, int $submitted): void
    {
        abort_unless($current === $submitted, 409, 'This record changed. Refresh before saving.');
    }

    public function decide(AdminActionRequest $request, User $technician)
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $technician, $data) {
            abort_unless($technician->role->value === 'technician', 404);
            $p = TechnicianProfile::where('user_id', $technician->id)->lockForUpdate()->first();
            if (! $p) {
                throw ValidationException::withMessages(['decision' => 'This technician needs to complete their trade and area profile first.']);
            }
            $this->version($p->verification_version, (int) $data['version']);
            if ($data['decision'] === 'approved' && ($technician->documents->isEmpty() || $technician->documents->contains(fn ($d) => ! Storage::disk('private')->exists($d->storage_path)))) {
                throw ValidationException::withMessages(['decision' => 'Protected identity documents must be supplied before approval.']);
            }
            $from = $p->kyc_status;
            $p->kyc_status = $data['decision'] === 'approved' ? 'verified' : $data['decision'];
            $p->is_active = $data['decision'] === 'approved';
            $p->verified_at = $data['decision'] === 'approved' ? now() : null;
            $p->verification_version++;
            $p->save();
            foreach ($technician->documents()->where('status', 'pending')->get() as $document) {
                $document->update(['status' => $data['decision']]);
                DB::table('kyc_decisions')->insert(['kyc_document_id' => $document->id, 'reviewer_id' => $request->user()->id, 'status' => $data['decision'], 'reason' => $data['reason'], 'created_at' => now()]);
            }
            DB::table('journey_notifications')->insert(['user_id' => $technician->id, 'type' => 'verification.decided', 'body' => $data['decision'].': '.$data['reason'], 'created_at' => now()]);
            TechnicianDecision::create(['technician_profile_id' => $p->id, 'reviewer_id' => $request->user()->id,
                'from_status' => $from, 'to_status' => $data['decision'], 'reason' => $data['reason'], 'created_at' => now()]);
            Audit::record('technician.verification_decided', $p, ['decision' => $data['decision'], 'reason' => $data['reason']]);
        }, 3);

        return back()->with('status', 'Verification decision saved.');
    }

    public function assign(AdminActionRequest $request, Booking $booking, EligibilityService $eligibility)
    {
        $data = $request->validated();
        DB::transaction(function () use ($booking, $data, $eligibility) {
            MarketplaceSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->version($b->lock_version, (int) $data['version']);
            $this->unpaidRequest($b);
            abort_if($b->technician_id !== null, 409, 'Return the request to the queue before assigning another technician.');
            $tech = User::findOrFail($data['technician_id']);
            TechnicianProfile::where('user_id', $tech->id)->lockForUpdate()->first();
            if (! $eligibility->matches($tech, $b->service_category, $data['service_area'])
                || ($b->service_area && mb_strtolower(trim($b->service_area)) !== mb_strtolower(trim($data['service_area'])))) {
                throw ValidationException::withMessages(['technician_id' => 'Choose an approved, active, available technician matching the category and confirmed service area.']);
            }
            $b->update(['technician_id' => $tech->id, 'request_accepted_at' => null, 'service_area' => $tech->technicianProfile->service_location, 'lock_version' => $b->lock_version + 1]);
            Audit::record('booking.assigned', $b, ['technician_id' => $tech->id, 'service_area' => $b->service_area, 'reason' => $data['reason']]);
        }, 3);

        return back()->with('status', 'Technician assigned.');
    }

    private function unpaidRequest(Booking $b): void
    {
        abort_unless($b->status === BookingStatus::Requested && ! $b->quotation()->exists()
            && ! $b->payment()->whereNotIn('status', ['failed', 'cancelled'])->exists(), 409, 'Only an unpaid request without a quotation can be changed.');
    }

    public function close(AdminActionRequest $request, Booking $booking)
    {
        return $this->changeRequest($request, $booking, false);
    }

    public function requeue(AdminActionRequest $request, Booking $booking)
    {
        return $this->changeRequest($request, $booking, true);
    }

    private function changeRequest(AdminActionRequest $request, Booking $booking, bool $requeue)
    {
        $data = $request->validated();
        DB::transaction(function () use ($booking, $data, $requeue) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->version($b->lock_version, (int) $data['version']);
            $this->unpaidRequest($b);
            abort_unless($requeue ? $b->technician_id !== null : $b->technician_id === null, 409);
            $previous = $b->technician_id;
            $b->update(['technician_id' => null, 'request_accepted_at' => null, 'status' => $requeue ? BookingStatus::Requested : BookingStatus::Cancelled, 'lock_version' => $b->lock_version + 1]);
            Audit::record($requeue ? 'booking.requeued' : 'booking.closed', $b, ['previous_technician_id' => $previous, 'reason' => $data['reason']]);
        }, 3);

        return back()->with('status', $requeue ? 'Request returned to the assignment queue.' : 'Request closed.');
    }

    public function flag(AdminActionRequest $request, Booking $booking)
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $booking, $data) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->version($b->lock_version, (int) $data['version']);
            EvidenceFlag::create(['booking_id' => $b->id, 'work_round' => $b->current_work_round, 'actor_id' => $request->user()->id, 'reason' => $data['reason'], 'created_at' => now()]);
            $b->increment('lock_version');
            Audit::record('evidence.flagged', $b, ['work_round' => $b->current_work_round, 'reason' => $data['reason']]);
        }, 3);

        return back()->with('status', 'Evidence review note recorded.');
    }

    public function resolve(AdminActionRequest $request, Booking $booking, DisputeResolution $resolution)
    {
        $data = $request->validated();
        $dispute = $booking->disputes()->latest('id')->firstOrFail();
        $decision = ['rework_required' => 'rework', 'release_pending' => 'release', 'refund_pending' => 'refund'][$data['decision']];
        $resolution->resolve($dispute, $request->user(), $decision, $data['reason'], (int) $data['version']);

        return back()->with('status', 'Dispute decision saved.');
    }

    public function moderate(AdminActionRequest $request, Review $review)
    {
        $data = $request->validated();
        DB::transaction(function () use ($review, $data) {
            $r = Review::whereKey($review->id)->lockForUpdate()->firstOrFail();
            $this->version($r->version, (int) $data['version']);
            $r->update(['status' => $r->status === 'published' ? 'hidden' : 'published', 'moderation_reason' => $data['reason'], 'version' => $r->version + 1]);
            Audit::record('review.moderated', $r, ['status' => $r->status, 'reason' => $data['reason']]);
        }, 3);

        return back()->with('status', 'Review moderation saved.');
    }

    public function evidence(JobEvidence $evidence)
    {
        return $this->download($evidence->storage_path);
    }

    public function document(KycDocument $document)
    {
        return $this->download($document->storage_path);
    }

    private function download(string $path)
    {
        abort_unless(Storage::disk('private')->exists($path),404);

        return Storage::disk('private')->download($path,basename($path),['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
