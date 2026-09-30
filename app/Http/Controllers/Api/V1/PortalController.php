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

    public function technicians()
    {
        $users = app(EligibilityService::class)->query()->with('technicianProfile')->orderBy('name')->get();

        return response()->json(['data' => $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'service_category' => $u->technicianProfile->trade, 'service_area' => $u->technicianProfile->service_location]),
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
