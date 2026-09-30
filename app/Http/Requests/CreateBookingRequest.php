<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\EligibilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'customer' && $this->user()->is_active;
    }

    public function rules(): array
    {
        return ['technician_id' => 'nullable|integer|exists:users,id', 'service_category' => 'required|string|max:100',
            'service_area' => 'nullable|string|max:100', 'description' => 'required|string|min:20|max:5000',
            'address' => 'required|string|max:1000', 'scheduled_at' => 'nullable|date|after:now'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (! Category::where('normalized_name', Str::lower(trim($this->string('service_category'))))->where('active', true)->exists()) {
                $validator->errors()->add('service_category', 'Choose an active service category.');
            }
            if ($this->filled('service_area') && ! ServiceArea::where('normalized_name', Str::lower(trim($this->string('service_area'))))->where('active', true)->exists()) {
                $validator->errors()->add('service_area', 'Choose an active service area.');
            }
            if ($this->filled('technician_id') && ! app(EligibilityService::class)->matches(User::findOrFail($this->integer('technician_id')), $this->string('service_category'), $this->input('service_area'))) {
                $validator->errors()->add('technician_id', 'Choose a verified, active, available technician matching the service and area.');
            }
        }];
    }
}
