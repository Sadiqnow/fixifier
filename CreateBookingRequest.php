<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class CreateBookingRequest extends FormRequest { public function authorize():bool{return $this->user()?->role?->value==='customer';} public function rules():array{return ['technician_id'=>'nullable|exists:users,id','service_category'=>'required|string|max:100','description'=>'required|string|min:20|max:5000','address'=>'required|string|max:1000','scheduled_at'=>'nullable|date|after:now'];} }
