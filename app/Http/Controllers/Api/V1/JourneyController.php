<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\{Booking, Category, KycDocument, Quotation, ServiceArea, TechnicianProfile};
use App\Services\{EligibilityService, Journey};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema, Storage, Validator};
use Illuminate\Validation\Rule;

class JourneyController extends Controller
{
    public function profile(Request $r)
    {
        abort_unless($r->user()->role->value === 'technician', 403);

        $normalized = [
            'trade' => $r->input('trade', $r->input('category')),
            'service_location' => $r->input('service_location', $r->input('service_area', $r->input('city'))),
            'bio' => $r->input('bio', $r->input('description')),
            'skills' => $r->input('skills', $r->input('specialties')),
            'years_experience' => $r->input('years_experience', $r->input('experience_years', $r->input('experience'))),
            'indicative_price_minor' => $r->input('indicative_price_minor', $r->input('starting_price_minor', $r->input('rate_minor'))),
            'is_available' => $r->has('is_available') ? $r->boolean('is_available') : $r->boolean('availability', true),
            'availability_notes' => $r->input('availability_notes', $r->input('availability_note')),
        ];
        $r->merge(array_filter($normalized, fn ($value) => ! is_null($value)));

        $data = $r->validate([
            'trade' => ['required', Rule::in(Category::where('active', true)->pluck('name')->all())],
            'service_location' => ['required', Rule::in(ServiceArea::where('active', true)->pluck('name')->all())],
            'bio' => 'required|string|min:20|max:5000',
            'skills' => 'required|string|max:2000',
            'years_experience' => 'required|integer|between:0,80',
            'indicative_price_minor' => 'nullable|integer|between:0,100000000',
            'is_available' => 'required|boolean',
            'availability_notes' => 'nullable|string|max:2000',
        ]);

        $profileData = [
            'trade' => $data['trade'],
            'service_location' => $data['service_location'],
            'bio' => $data['bio'],
            'years_experience' => $data['years_experience'],
            'is_available' => $data['is_available'],
        ];

        if (Schema::hasColumn('technician_profiles', 'skills')) {
            $profileData['skills'] = $data['skills'];
        }
        if (Schema::hasColumn('technician_profiles', 'indicative_price_minor')) {
            $profileData['indicative_price_minor'] = $data['indicative_price_minor'];
        }
        if (Schema::hasColumn('technician_profiles', 'starting_price_minor')) {
            $profileData['starting_price_minor'] = $data['indicative_price_minor'] ?? null;
        }
        if (Schema::hasColumn('technician_profiles', 'availability_notes')) {
            $profileData['availability_notes'] = $data['availability_notes'] ?? null;
        }

        $p = DB::transaction(function () use ($r, $profileData) {
            \App\Models\User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $p = TechnicianProfile::firstOrNew(['user_id' => $r->user()->id]);
            if ($p->exists && ($p->trade !== $profileData['trade'] || $p->service_location !== $profileData['service_location'])) {
                $p->kyc_status = 'pending';
                $p->verified_at = null;
                if (Schema::hasColumn('technician_profiles', 'verification_version')) {
                    $p->verification_version = ($p->verification_version ?? 0) + 1;
                }
            }

            $p->fill(array_filter($profileData, fn ($value) => ! is_null($value)))->save();
            return $p;
        });

        return response()->json(['data' => $p]);
    }

    public function documents(Request $r)
    {
        abort_unless($r->user()->role->value === 'technician', 403);
        return response()->json(['data' => $r->user()->documents, 'decisions' => $r->user()->technicianProfile?->decisions]);
    }

    public function document(Request $r, KycDocument $document)
    {
        abort_unless($r->user()->role->value === 'admin' || $document->user_id === $r->user()->id, 403);
        return Storage::disk('private')->download($document->storage_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function submitDocument(Request $r)
    {
        abort_unless($r->user()->role->value === 'technician', 403);
        $data = $r->validate(['type' => 'required|string|max:50', 'document' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240']);
        $path = null;
        try {
            $doc = DB::transaction(function () use ($r, $data, &$path) {
                $p = TechnicianProfile::where('user_id', $r->user()->id)->lockForUpdate()->firstOrFail();
                abort_if(in_array($p->kyc_status, ['suspended', 'verified']), 409, 'Contact the administrator before replacing approved or suspended credentials.');
                $file = $data['document'];
                $path = $file->store('kyc/'.$r->user()->id, 'private');
                abort_unless($path, 500, 'Document storage failed.');
                $doc = KycDocument::create(['user_id' => $r->user()->id, 'type' => $data['type'], 'storage_path' => $path,
                    'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'status' => 'pending']);
                $p->update(['kyc_status' => 'pending', 'verification_version' => $p->verification_version + 1]);
                \App\Services\Audit::record('kyc.submitted', $doc);
                return $doc;
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('private')->delete($path);
            throw $e;
        }
        return response()->json(['data' => $doc], 201);
    }

    public function act(Request $r, Booking $b, string $action)
    {
        $result = DB::transaction(function () use ($r, $b, $action) {
            $b = Booking::whereKey($b->id)->lockForUpdate()->firstOrFail();
            Journey::participant($b, $r->user());
            $role = $r->user()->role->value;
            if (in_array($action, ['accept-request', 'decline-request'])) {
                Journey::participant($b, $r->user(), 'technician');
                abort_unless($b->status === BookingStatus::Requested && !$b->request_accepted_at && !$b->quotation()->exists(), 409, 'Request already handled.');
                if ($action === 'accept-request') {
                    abort_unless(app(EligibilityService::class)->matches($r->user(), $b->service_category, $b->service_area), 422, 'Your profile is not eligible for this request.');
                    $b->request_accepted_at = now();
                    $body = 'Technician accepted the request.';
                } else {
                    $body = $r->validate(['reason' => 'required|string|min:10|max:2000'])['reason'];
                    $b->technician_id = null;
                    Journey::updateRound($b, ['technician_id' => null]);
                }
            } elseif (in_array($action, ['revise-quote', 'decline-quote'])) {
                Journey::participant($b, $r->user(), 'customer');
                abort_unless($b->status === BookingStatus::Quoted, 409, 'Quotation is not awaiting a decision.');
                $data = $r->validate(['quotation_id' => 'required|integer', 'reason' => 'required|string|min:10|max:2000']);
                $q = $b->quotation()->firstOrFail();
                abort_unless($q->id === (int) $data['quotation_id'] && !$q->accepted_at, 409, 'Quotation changed.');
                $q->update(['decision' => $action, 'decision_reason' => $data['reason']]);
                $body = $data['reason'];
                $b->status = $action === 'revise-quote' ? BookingStatus::Requested : BookingStatus::Cancelled;
            } elseif ($action === 'schedule') {
                Journey::participant($b, $r->user(), 'technician');
                abort_unless($b->request_accepted_at && in_array($b->status, [BookingStatus::Requested, BookingStatus::Quoted, BookingStatus::Confirmed]), 409, 'Scheduling is unavailable at this stage.');
                $data = $r->validate(['scheduled_at' => 'required|date|after:now', 'reason' => 'required|string|min:10|max:2000']);
                $b->scheduled_at = $data['scheduled_at'];
                $b->visit_status = 'scheduled';
                Journey::updateRound($b, ['scheduled_at' => $b->scheduled_at]);
                $body = $data['reason'];
            } elseif ($action === 'visit') {
                Journey::participant($b, $r->user(), 'technician');
                abort_unless($b->request_accepted_at && in_array($b->status, [BookingStatus::Requested, BookingStatus::Quoted, BookingStatus::Confirmed]), 409);
                $data = $r->validate(['status' => 'required|in:en_route,arrived,inspection', 'note' => 'required|string|min:10|max:2000']);
                $next = ['scheduled' => 'en_route', 'en_route' => 'arrived', 'arrived' => 'inspection'];
                abort_unless(($next[$b->visit_status] ?? null) === $data['status'], 409, 'Confirm an appointment and follow en-route, arrived, inspection order.');
                $b->visit_status = $data['status'];
                $body = $data['note'];
            } elseif (in_array($action, ['message', 'progress', 'dispute-response'])) {
                abort_unless(in_array($role, ['technician', 'customer', 'admin']), 403);
                if ($action === 'progress') {
                    Journey::participant($b, $r->user(), 'technician');
                    abort_unless($b->status === BookingStatus::InProgress, 409);
                }
                if ($action === 'dispute-response') {
                    Journey::participant($b, $r->user(), 'technician');
                    abort_unless($b->status === BookingStatus::Disputed && $b->disputes()->where('status', 'open')->exists(), 409);
                }
                $body = $r->validate(['body' => 'required|string|min:2|max:5000'])['body'];
            } else abort(404);
            $b->lock_version++;
            $b->save();
            Journey::record($b, $action, $body, ['scheduled_at' => $b->scheduled_at, 'visit_status' => $b->visit_status]);
            return $b;
        }, 3);
        return response()->json(['data' => $result]);
    }

    public function upload(Request $r, Booking $b)
    {
        $path = null;
        try {
            $id = DB::transaction(function () use ($r, $b, &$path) {
                $b = Booking::whereKey($b->id)->lockForUpdate()->firstOrFail();
                Journey::participant($b, $r->user());
                $data = $r->validate(['type' => 'required|in:fault,dispute', 'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240']);
                if ($data['type'] === 'fault') {
                    Journey::participant($b, $r->user(), 'customer');
                    abort_unless($b->status === BookingStatus::Requested, 409);
                } else abort_unless($b->status === BookingStatus::Disputed, 409, 'Open a dispute before adding its attachments.');
                $file = $data['file'];
                $path = $file->store('bookings/'.$b->id.'/attachments', 'private');
                abort_unless($path, 500, 'Attachment storage failed.');
                $id = DB::table('booking_attachments')->insertGetId(['booking_id' => $b->id, 'uploader_id' => $r->user()->id,
                    'dispute_id' => $data['type'] === 'dispute' ? $b->disputes()->where('status', 'open')->firstOrFail()->id : null,
                    'work_round' => $b->current_work_round, 'type' => $data['type'], 'storage_path' => $path,
                    'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'created_at' => now()]);
                Journey::record($b, 'attachment.uploaded', 'A private '.$data['type'].' attachment was added.');
                return $id;
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('private')->delete($path);
            throw $e;
        }
        return response()->json(['data' => ['id' => $id]], 201);
    }

    public function attachment(Request $r, int $id)
    {
        $a = DB::table('booking_attachments')->where('id', $id)->first();
        abort_unless($a, 404);
        Journey::participant(Booking::findOrFail($a->booking_id), $r->user());
        return Storage::disk('private')->download($a->storage_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function notifications(Request $r)
    {
        return response()->json(['data' => DB::table('journey_notifications')->where('user_id', $r->user()->id)->latest('id')->limit(100)->get()]);
    }

    public function readNotification(Request $r, int $id)
    {
        abort_unless(DB::table('journey_notifications')->where('id', $id)->where('user_id', $r->user()->id)->update(['read_at' => now()]), 404);
        return response()->json(['message' => 'Read']);
    }
}
