<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DisputeResolution
{
    public function resolve(Dispute $bound, User $actor, string $decision, string $reason, ?int $version = null): Dispute
    {
        abort_unless($actor->role->value === 'admin' && $actor->is_active, 403);

        return DB::transaction(function () use ($bound, $actor, $decision, $reason, $version) {
            // All workflow actions take the booking lock first, then the dispute/payment lock.
            $booking = Booking::whereKey($bound->booking_id)->lockForUpdate()->firstOrFail();
            $dispute = Dispute::whereKey($bound->id)->lockForUpdate()->firstOrFail();
            abort_if($version !== null && $booking->lock_version !== $version, 409, 'Booking changed. Refresh before deciding.');
            abort_unless($booking->status === BookingStatus::Disputed && $dispute->status === 'open'
                && $dispute->work_round === $booking->current_work_round, 409, 'This dispute is no longer open for the current round.');
            abort_unless(in_array($decision, ['rework', 'release', 'refund'], true), 422);
            $dispute->update(['status' => 'resolved', 'decision' => $decision, 'resolution' => $reason,
                'resolved_by' => $actor->id, 'resolved_at' => now()]);
            $booking->lock_version++;
            if ($decision === 'rework') {
                $booking->current_work_round++;
                $booking->status = BookingStatus::Confirmed;
                $booking->settlement_status = null;
            } else {
                $booking->status = $decision === 'release' ? BookingStatus::Completed : BookingStatus::Cancelled;
                $booking->settlement_status = $decision === 'release' ? 'release_pending' : 'refund_pending';
                if ($decision === 'release') {
                    $booking->release_approved_at = now();
                }
                // A ruling requests settlement. It is never evidence of provider money movement.
                $booking->payment()->where('status', 'authorized')->update(['status' => $booking->settlement_status]);
            }
            $booking->save();
            Audit::record('dispute.resolved', $dispute, ['booking_id' => $booking->id, 'work_round' => $dispute->work_round, 'decision' => $decision, 'reason' => $reason]);

            return $dispute;
        }, 3);
    }
}
