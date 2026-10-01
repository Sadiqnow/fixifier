<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class SubmitQuotationRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role?->value === 'technician'; }
    public function rules(): array {
        return ['currency'=>'required|in:NGN', 'scope'=>'required|string|min:10|max:5000',
            'diagnosis'=>'required|string|min:10|max:5000', 'exclusions'=>'required|string|max:5000',
            'duration_minutes'=>'required|integer|between:1,43200', 'expires_at'=>'required|date|after:now',
            'items'=>'required|array|min:1|max:50', 'items.*.description'=>'required|string|max:200',
            'items.*.kind'=>'required|in:labour,materials,charge', 'items.*.quantity'=>'required|integer|between:1,1000',
            'items.*.unit_price_minor'=>'required|integer|between:0,100000000'];
    }
}
