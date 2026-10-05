<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBookingRequest;
use App\Models\Booking;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use App\Models\ServiceArea;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\Audit;
use App\Services\EligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        $query = Booking::with(['quotation', 'payment', 'review'])->latest();
        if ($r->user()->role->value === 'customer') {
            $query->where('customer_id', $r->user()->id);
        } elseif ($r->user()->role->value === 'technician') {
            $query->where('technician_id', $r->user()->id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(CreateBookingRequest $r, EligibilityService $eligibility): JsonResponse
    {
        $booking = DB::transaction(function () use ($r, $eligibility) {
            MarketplaceSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $data = $r->validated();
            $category = Category::where('normalized_name', Str::lower(trim($data['service_category'])))->where('active', true)->first();
            if (! $category) {
                throw ValidationException::withMessages(['service_category' => 'This service is no longer active.']);
            }
            $data['service_category'] = $category->name;
            if (! empty($data['service_area'])) {
                $area = ServiceArea::where('normalized_name', Str::lower(trim($data['service_area'])))->where('active', true)->first();
                if (! $area) {
                    throw ValidationException::withMessages(['service_area' => 'This service area is no longer active.']);
                }
                $data['service_area'] = $area->name;
            }
            if (! empty($data['technician_id'])) {
                TechnicianProfile::where('user_id', $data['technician_id'])->lockForUpdate()->first();
                $tech = User::findOrFail($data['technician_id']);
                if (! $eligibility->matches($tech, $data['service_category'], $data['service_area'] ?? null)) {
                    throw ValidationException::withMessages(['technician_id' => 'This technician is no longer eligible.']);
                }
                $data['service_area'] = $tech->technicianProfile->service_location;
                if (!empty($data['scheduled_at'])) {
                    app(\App\Services\TechnicianAvailabilityService::class)->assertBookable($tech, \Carbon\CarbonImmutable::parse($data['scheduled_at']), 60, 15);
                }
            }
            $booking = Booking::create($data + ['reference' => 'FX-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'customer_id' => $r->user()->id, 'status' => BookingStatus::Requested]);
            if ($booking->scheduled_at) {
                $booking->scheduled_end_at = $booking->scheduled_at->copy()->addHour();
                $booking->travel_buffer_minutes = 15;
                $booking->save();
            }
            \App\Services\Journey::round($booking);
            \App\Services\Journey::record($booking, 'request.created', 'New service request.');
            Audit::record('booking.created', $booking);

            return $booking;
        }, 3);

        return response()->json(['data' => $booking], 201);
    }

    public function show(Request $r, Booking $booking): JsonResponse
    {
        $user = $r->user();
        abort_unless($user->role->value === 'admin' || $booking->customer_id === $user->id || $booking->technician_id === $user->id, 403);

        $booking->setAttribute('quote_versions', \App\Models\Quotation::where('booking_id', $booking->id)->orderBy('version')->get());
        foreach (['work_rounds', 'booking_updates', 'booking_attachments'] as $table) {
            $rows = DB::table($table)->where('booking_id', $booking->id)->orderBy('id')->get();
            if ($table === 'booking_attachments') $rows->each(function ($row) { unset($row->storage_path); });
            $booking->setAttribute($table, $rows);
        }
        return response()->json(['data' => $booking->load(['customer', 'technician', 'quotation', 'evidence', 'dispute', 'disputes', 'payment', 'review'])]);
    }
}
