<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TechnicianScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->value === 'technician';
    }

    public function rules(): array
    {
        return [
            'version' => 'required|integer|min:1',
            'timezone' => 'required|timezone',
            'weekly' => 'present|array|max:42',
            'weekly.*' => 'required|array:weekday,starts_at,ends_at',
            'weekly.*.weekday' => 'required|integer|between:0,6',
            'weekly.*.starts_at' => 'required|date_format:H:i',
            'weekly.*.ends_at' => 'required|date_format:H:i|after:weekly.*.starts_at',
            'exceptions' => 'present|array|max:100',
            'exceptions.*' => 'required|array:starts_at,ends_at,available,reason',
            'exceptions.*.starts_at' => 'required|date',
            'exceptions.*.ends_at' => 'required|date|after:exceptions.*.starts_at',
            'exceptions.*.available' => 'required|boolean',
            'exceptions.*.reason' => 'required|string|max:250',
        ];
    }
}
