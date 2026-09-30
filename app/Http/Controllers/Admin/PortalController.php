<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Category;
use App\Models\MarketplaceSetting;
use App\Models\Payment;
use App\Models\Review;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\AdminPortalData;
use App\Services\EarningsCalculator;
use App\Services\RankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;

class PortalController extends Controller
{
    public function show(Request $request, EarningsCalculator $calculator, RankingService $ranking, AdminPortalData $data)
    {
        $page = $request->route('page');
        abort_unless(array_key_exists($page, config('fixifier.navigation')), 404);
        $settings = MarketplaceSetting::find(1);
        if (! $settings) {
            return response()->view('admin.install', [], 503);
        }
        $s = $settings->values;
        $categories = Category::orderBy('name')->get();
        $areas = ServiceArea::orderBy('name')->get();
        $technicians = User::where('role', 'technician')->with(['technicianProfile.decisions', 'reviews', 'assignedBookings', 'documents'])->get()->map(fn ($u) => $data->technician($u));
        $rawAudit = AuditEvent::latest('id')->limit(1000)->get();
        $metrics = [
            'total' => Booking::count(), 'unassigned' => Booking::where('status', 'requested')->whereNull('technician_id')->count(),
            'disputed' => Booking::where('status', 'disputed')->count(), 'review' => Booking::where('status', 'evidence_submitted')->count(),
            'rework' => Booking::where('current_work_round', '>', 1)->count(),
            'settlementPending' => Booking::whereIn('settlement_status', ['release_pending', 'refund_pending'])->count(),
        ];
        $section = $request->string('status', 'all')->toString();
        $query = Booking::with(['customer', 'quotation', 'evidence', 'flags', 'disputes', 'review'])->latest('id');
        if ($page === 'requests') {
            $query->where('status', 'requested');
        }
        if ($page === 'evidence') {
            $query->where(fn ($q) => $q->has('evidence')->orWhere('current_work_round', '>', 1));
        }
        if ($page === 'disputes') {
            $query->has('disputes');
        }
        if ($page === 'jobs' && $section !== 'all') {
            match ($section) {
                'requested' => $query->where('status', 'requested')->whereNull('technician_id'),
                'awaiting_verification' => $query->where('status', 'evidence_submitted'),
                'disputed' => $query->where('status', 'disputed'),
                'completed' => $query->where('status', 'completed'),
                default => abort(422, 'Unknown booking filter.'),
            };
        }
        $bookingPage = $query->paginate(50, ['*'], 'bookings_page')->withQueryString();
        $rawBookings = $bookingPage->getCollection();
        // An explicit selection remains inspectable even when older than the queue window.
        if ($request->integer('booking') && ! $rawBookings->contains('id', $request->integer('booking'))) {
            $selected = Booking::with(['customer', 'quotation', 'evidence', 'flags', 'disputes', 'review'])->findOrFail($request->integer('booking'));
            $rawBookings->push($selected);
        }
        $bookings = $rawBookings->map(fn ($b) => $data->booking($b, $technicians, $rawAudit));
        $current = $bookings->firstWhere('number', $request->integer('booking')) ?? $bookings->first();
        $reviews = Review::with(['technician', 'booking'])->latest('id')->limit(250)->get();
        $actors = User::whereIn('id', $rawAudit->pluck('actor_id')->filter())->pluck('name', 'id');
        $audit = $rawAudit->take(250)->map(fn ($e) => new Fluent(['action' => $e->action,
            'target' => $e->metadata['target'] ?? class_basename($e->subject_type).' #'.$e->subject_id,
            'actor_name' => $actors[$e->actor_id] ?? 'System', 'created_at' => $e->created_at]));
        $events = Payment::with('booking')->latest('id')->limit(250)->get()->map(fn ($p) => new Fluent([
            'type' => $p->provider, 'booking' => new Fluent(['number' => $p->booking_id]),
            'amount_minor' => $p->amount_minor, 'status' => $p->status, 'reference' => $p->provider_reference,
        ]));

        return view('admin.'.$page, compact('page', 'settings', 's', 'categories', 'areas', 'technicians', 'bookings', 'current', 'section', 'reviews', 'audit', 'events', 'calculator', 'ranking', 'metrics', 'bookingPage'));
    }

    public function calculation(Request $request, EarningsCalculator $calculator)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2']]);
        $s = MarketplaceSetting::findOrFail(1)->values;
        $c = $calculator->calculate((int) round($data['amount'] * 100),$s);

        return view('partials.calculation',compact('c','s'));
    }
}
