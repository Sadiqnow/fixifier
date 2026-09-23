<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JobEvidence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    public function me(Request $request)
    {
        return response()->json(['data' => $request->user(), 'technician_profile' => $request->user()->role->value === 'technician'
            ? \Illuminate\Support\Facades\DB::table('technician_profiles')->where('user_id', $request->user()->id)->first() : null]);
    }

    public function technicians()
    {
        return response()->json(['data' => User::where('role', 'technician')->orderBy('name')->get(['id', 'name'])]);
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
