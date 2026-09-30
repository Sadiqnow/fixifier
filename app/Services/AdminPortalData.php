<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\JobEvidence;
use App\Models\Quotation;
use App\Models\User;
use App\Support\AdminBooking;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

class AdminPortalData
{
    public function technician(User $user): Fluent
    {
        $p = $user->technicianProfile;
        $reviews = $user->reviews->where('status', 'published');
        $kyc = match ($p?->kyc_status) {
            'verified' => 'approved', 'pending', null => 'pending_review', default => $p->kyc_status
        };
        if (! $user->is_active || ($p && ! $p->is_active)) {
            $kyc = 'suspended';
        }

        return new Fluent([
            'id' => $user->id, 'name' => $user->name,
            'category' => new Fluent(['name' => $p?->trade ?? 'No trade recorded']),
            'area' => new Fluent(['name' => $p?->service_location ?? 'No area recorded']),
            'service_area_id' => $p?->service_location ?? '', 'kyc' => $kyc,
            'available' => (bool) ($p?->is_available && $p?->is_active && $user->is_active),
            'version' => $p?->verification_version ?? 1,
            'rating_count' => $reviews->count(), 'rating' => round((float) $reviews->avg('stars'), 1),
            'completed' => $user->assignedBookings->where('status', BookingStatus::Completed)->count(),
            'documents' => $user->documents->map(fn ($d) => new Fluent(['id' => $d->id, 'path' => $d->storage_path, 'is_sample' => false, 'label' => $d->type])),
            'decisions' => $p?->decisions ?? collect(),
        ]);
    }

    public function booking(Booking $b, Collection $technicians, Collection $audit): AdminBooking
    {
        $status = match ($b->status->value) {
            'requested' => $b->technician_id ? 'awaiting_quote' : 'requested',
            'quoted' => 'awaiting_customer',
            'confirmed' => $b->current_work_round > 1 ? 'rework_required' : 'confirmed',
            'evidence_submitted' => 'awaiting_verification',
            default => $b->status->value,
        };
        if (in_array($b->status->value, ['completed', 'cancelled']) && $b->settlement_status) {
            $status = $b->settlement_status;
        }
        $rounds = collect(range(1, $b->current_work_round))->map(fn ($n) => new Fluent([
            'number' => $n,
            'evidence' => $b->evidence->where('work_round', $n)->map(fn ($e) => new Fluent([
                'id' => $e->id, 'phase' => $e->type, 'path' => $e->storage_path, 'is_sample' => false,
                'filename' => basename($e->storage_path),
            ])),
            'flags' => $b->flags->where('work_round', $n),
        ]));
        $subjects = [Booking::class => [$b->id], Quotation::class => [$b->quotation?->id],
            JobEvidence::class => $b->evidence->modelKeys(), Dispute::class => $b->disputes->modelKeys()];
        $events = $audit->filter(fn ($e) => in_array($e->subject_id, $subjects[$e->subject_type] ?? [], true))
            ->map(fn ($e) => new Fluent(['description' => $e->created_at->format('d M H:i').' · '.$e->action
                .(isset($e->metadata['reason']) ? ' · '.$e->metadata['reason'] : '')]));

        return new AdminBooking([
            'id' => $b->id, 'number' => $b->id, 'reference' => $b->reference, 'title' => $b->service_category,
            'customer_name' => $b->customer?->name ?? 'Unavailable customer', 'description' => $b->description,
            'address' => $b->address, 'category' => new Fluent(['name' => $b->service_category]),
            'area' => new Fluent(['name' => $b->service_area ?? 'Confirm service area']), 'service_area_id' => $b->service_area ?? '',
            'technician' => $technicians->firstWhere('id', $b->technician_id),
            'amount_minor' => $b->quotation?->amount_minor ?? 0, 'amount' => ($b->quotation?->amount_minor ?? 0) / 100,
            'status' => $status, 'booking_status' => $b->status->value, 'version' => $b->lock_version,
            'current_round' => $b->current_work_round, 'rounds' => $rounds, 'events' => $events,
            'disputes' => $b->disputes->map(fn ($d) => new Fluent(['id' => $d->id, 'concern' => $d->reason.' — '.$d->details, 'resolution_reason' => $d->resolution])),
            'review' => $b->review, 'is_demo' => false,
        ]);
    }
}
