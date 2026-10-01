<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\JobEvidence;
use App\Models\ServiceArea;
use App\Services\EligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    public function me(Request $request)
    {
        return response()->json(['data' => $request->user(), 'technician_profile' => $request->user()->role->value === 'technician'
            ? DB::table('technician_profiles')->where('user_id', $request->user()->id)->first() : null]);
    }

    public function technicians(Request $request)
    {
        $users = app(EligibilityService::class)->query()->with('technicianProfile')->withCount(['assignedBookings as completed_work' => fn ($q) => $q->where('status', 'completed'), 'reviews' => fn ($q) => $q->where('status', 'published')])->withAvg(['reviews' => fn ($q) => $q->where('status', 'published')], 'stars')->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.mb_substr($request->input('search'), 0, 100).'%'))->orderBy('name')->get();

        return response()->json(['data' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'service_category' => $u->technicianProfile->trade, 'service_area' => $u->technicianProfile->service_location, 'profile' => $u->technicianProfile, 'completed_work' => $u->completed_work, 'rating' => $u->reviews_avg_stars, 'rating_count' => $u->reviews_count]),
            'categories' => Category::where('active', true)->orderBy('name')->pluck('name'),
            'areas' => ServiceArea::where('active', true)->orderBy('name')->pluck('name')]);
    }

    public function evidence(Request $request, JobEvidence $evidence)
    {
        $booking = $evidence->booking;
        $user = $request->user();
        abort_unless($user->role->value === 'admin' || $booking->customer_id === $user->id || $booking->technician_id === $user->id, 403);
        abort_unless(Storage::disk('private')->exists($evidence->storage_path), 404);

        return Storage::disk('private')->response($evidence->storage_path, null, ['Cache-Control' => 'private, no-store']);
    }
}
