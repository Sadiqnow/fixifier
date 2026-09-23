<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class SubmitQuotationRequest extends FormRequest { public function authorize():bool{return $this->user()?->role?->value==='technician';} public function rules():array{return ['amount_minor'=>'required|integer|min:10000','currency'=>'required|in:NGN','scope'=>'required|string|min:10|max:5000','expires_at'=>'required|date|after:now'];} }
