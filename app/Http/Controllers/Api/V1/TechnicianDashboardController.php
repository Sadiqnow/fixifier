<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Booking, Payment, Review};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TechnicianDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->role->value === 'technician', 403);
        $id = $request->user()->id;
        $bookings = Booking::where('technician_id', $id);
        $counts = (clone $bookings)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $reviews = Review::where('technician_id', $id)->where('status', 'published');
        $ratingCount = (clone $reviews)->count();
        $payments = Payment::whereHas('booking', fn ($q) => $q->where('technician_id', $id));

        return response()->json(['data' => [
            'counts' => $counts,
            'total_bookings' => $counts->sum(),
            'active_jobs' => $counts->except(['completed', 'cancelled'])->sum(),
            'completed_jobs' => (int) ($counts['completed'] ?? 0),
            'rating_count' => $ratingCount,
            'rating_average' => $ratingCount ? round((clone $reviews)->avg('stars'), 2) : null,
            'recorded_releases_minor' => (int) (clone $payments)->where('status', 'released')->sum('amount_minor'),
            'unread_notifications' => DB::table('journey_notifications')->where('user_id', $id)->whereNull('read_at')->count(),
            'upcoming' => (clone $bookings)->whereNotIn('status', ['cancelled', 'completed'])->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')->limit(5)->get(['id', 'reference', 'service_category', 'scheduled_at', 'status']),
            'recent_reviews' => (clone $reviews)->latest('id')->limit(5)->get(['id', 'booking_id', 'stars', 'comment', 'created_at']),
        ]]);
    }
}
