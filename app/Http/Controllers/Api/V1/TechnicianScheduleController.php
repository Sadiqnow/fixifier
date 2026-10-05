<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnicianScheduleRequest;
use App\Services\TechnicianAvailabilityService;
use Illuminate\Http\Request;

class TechnicianScheduleController extends Controller
{
    public function slots(Request $request, \App\Models\User $technician, TechnicianAvailabilityService $availability)
    {
        abort_unless(in_array($request->user()->role->value, ['customer', 'technician', 'admin']), 403);
        abort_unless(app(\App\Services\EligibilityService::class)->query()->whereKey($technician->id)->exists(), 404);
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now()->addDays(90)->toDateString(), 'duration_minutes' => 'nullable|integer|between:1,1440', 'travel_buffer_minutes' => 'nullable|integer|between:0,240']);
        return response()->json(['data' => $availability->slots($technician, $data['date'], $data['duration_minutes'] ?? 60, $data['travel_buffer_minutes'] ?? 15)]);
    }

    public function index(Request $request, TechnicianAvailabilityService $availability)
    {
        abort_unless($request->user()->role->value === 'technician', 403);
        return response()->json(['data' => $availability->calendar($request->user())]);
    }

    public function update(TechnicianScheduleRequest $request, TechnicianAvailabilityService $availability)
    {
        return response()->json(['data' => $availability->save($request->user(), $request->validated())]);
    }
}
