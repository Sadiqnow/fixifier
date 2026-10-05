<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $attributes = ['lock_version' => 1, 'current_work_round' => 1];

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(EvidenceFlag::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    protected $fillable = ['reference', 'customer_id', 'technician_id', 'service_category', 'description', 'address', 'scheduled_at', 'status', 'service_area', 'lock_version', 'current_work_round', 'settlement_status', 'release_approved_at', 'request_accepted_at', 'visit_status', 'accepted_quotation_id'];

    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'current_work_round' => 'integer', 'release_approved_at' => 'datetime', 'status' => BookingStatus::class, 'scheduled_at' => 'datetime', 'scheduled_end_at' => 'datetime', 'travel_buffer_minutes' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class)->latestOfMany();
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(JobEvidence::class);
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class)->latestOfMany();
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
