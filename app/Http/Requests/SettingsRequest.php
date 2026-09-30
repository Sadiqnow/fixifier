<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'admin' && $this->user()?->is_active;
    }

    public function rules(): array
    {
        $percent = ['required', 'numeric', 'min:0', 'max:100', 'multiple_of:0.1'];
        $money = ['required', 'integer', 'min:0', 'max:100000000'];

        return ['version' => ['required', 'integer', 'min:1']] + match ($this->route('section')) {
            'setup' => ['name' => ['required', 'string', 'max:100'], 'city' => ['required', 'string', 'max:100'],
                'routing' => ['required', 'in:customer_choice,admin_assignment,ranked_suggestion'],
                'requestTimeout' => ['required', 'integer', 'between:1,168'], 'reviewHours' => ['required', 'integer', 'between:1,720'], 'autoApprove' => ['required', 'boolean']],
            'ranking' => ['ratingWeight' => ['required', 'integer', 'between:0,100'], 'distanceWeight' => ['required', 'integer', 'between:0,100'],
                'availabilityWeight' => ['required', 'integer', 'between:0,100'], 'completionWeight' => ['required', 'integer', 'between:0,100']],
            'escrow' => ['custody' => ['required', 'in:provider_hold,direct_collection'], 'capture' => ['required', 'in:on_quote_acceptance'],
                'release' => ['required', 'in:customer_approval'], 'holdHours' => ['required', 'integer', 'between:0,720'],
                'reviewHours' => ['required', 'integer', 'between:1,720'], 'autoApprove' => ['required', 'boolean']],
            'finance' => ['feePercent' => $percent, 'feeFixed' => $money, 'providerFeePercent' => $percent, 'providerFeeFixed' => $money,
                'providerFeePayer' => ['required', 'in:platform,technician'], 'reservePercent' => $percent, 'payoutBatch' => ['required', 'in:daily,weekly,manual'], 'minPayout' => $money],
            default => [],
        };
    }
}
