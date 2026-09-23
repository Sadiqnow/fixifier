<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPortalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role->value === 'admin', 403);
        $data = $request->validate(['section' => 'required|in:overview,technicians,bookings,evidence,disputes,customers,finance,audit']);
        $section = $data['section'];
        $summary = [
            'Technicians' => DB::table('users')->where('role', 'technician')->count(),
            'Bookings' => DB::table('bookings')->count(),
            'Open disputes' => DB::table('disputes')->where('status', 'open')->count(),
            'Pending KYC' => DB::table('technician_profiles')->where('kyc_status', 'pending')->count(),
        ];
        $query = match ($section) {
            'technicians' => DB::table('users')->leftJoin('technician_profiles', 'users.id', '=', 'technician_profiles.user_id')->where('users.role', 'technician')->select('users.id', 'users.name', 'users.email', 'technician_profiles.trade', 'technician_profiles.kyc_status')->orderByDesc('users.id'),
            'customers' => DB::table('users')->where('role', 'customer')->select('id', 'name', 'email', 'phone', 'created_at')->orderByDesc('id'),
            'finance' => DB::table('payments')->select('id', 'booking_id', 'provider', 'amount_minor', 'currency', 'status')->orderByDesc('id'),
            'audit', 'overview' => DB::table('audit_events')->select('id', 'actor_id', 'action', 'subject_type', 'subject_id', 'created_at')->orderByDesc('id'),
            default => Booking::with(['customer:id,name', 'technician:id,name', 'quotation', 'dispute', 'payment'])->when($section === 'disputes', fn ($q) => $q->has('dispute'))->when($section === 'evidence', fn ($q) => $q->has('evidence'))->latest(),
        };

        return response()->json(['summary' => $summary, 'records' => $query->paginate(20)]);
    }
}
