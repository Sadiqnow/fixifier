<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'admin' && $this->user()?->is_active;
    }

    public function rules(): array
    {
        $reason = ['required', 'string', 'min:10', 'max:3000'];
        $version = ['required', 'integer', 'min:1'];
        $base = ['version' => $version];

        return match ($this->route()->getName()) {
            'admin.catalog.store' => ['name' => ['required', 'string', 'max:50']],
            'admin.catalog.toggle' => ['active' => ['required', 'boolean']],
            'admin.technicians.decide' => $base + ['decision' => ['required', 'in:approved,changes_requested,rejected,suspended'], 'reason' => $reason],
            'admin.bookings.assign' => $base + ['service_area' => ['required', 'string', 'max:100'], 'technician_id' => ['required', 'integer', 'exists:users,id'], 'reason' => $reason],
            'admin.bookings.close', 'admin.bookings.requeue', 'admin.evidence.flag', 'admin.reviews.moderate' => $base + ['reason' => $reason],
            'admin.disputes.resolve' => $base + ['decision' => ['required', 'in:rework_required,release_pending,refund_pending'], 'reason' => $reason],
            default => [],
        };
    }
}
