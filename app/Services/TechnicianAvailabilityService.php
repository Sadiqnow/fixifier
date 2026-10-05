<?php

namespace App\Services;

use App\Models\{Booking, TechnicianProfile, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicianAvailabilityService
{
    public function slots(User $technician, string $date, int $minutes = 60, int $buffer = 15): array
    {
        $timezone = $technician->technicianProfile?->schedule_timezone ?? 'Africa/Lagos';
        if (!$technician->technicianProfile?->schedule_configured) {
            return ['timezone' => $timezone, 'configured' => false, 'duration_minutes' => $minutes, 'travel_buffer_minutes' => $buffer, 'slots' => []];
        }
        $day = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $slots = [];
        DB::transaction(function () use ($technician, $day, $minutes, $buffer, &$slots) {
            for ($start = $day; $start->lt($day->addDay()); $start = $start->addMinutes(30)) {
                if ($start->isPast()) continue;
                try {
                    $this->assertBookable($technician, $start, $minutes, $buffer);
                    $slots[] = ['starts_at' => $start->utc()->toIso8601String(), 'ends_at' => $start->addMinutes($minutes)->utc()->toIso8601String()];
                } catch (ValidationException $e) {
                    // Exclude unavailable windows. The actual reservation repeats this check under lock.
                }
            }
        });
        return ['timezone' => $timezone, 'configured' => true, 'duration_minutes' => $minutes, 'travel_buffer_minutes' => $buffer, 'slots' => $slots];
    }

    public function calendar(User $user): array
    {
        $p = $user->technicianProfile;
        return [
            'timezone' => $p?->schedule_timezone ?? 'Africa/Lagos',
            'configured' => (bool) $p?->schedule_configured,
            'version' => $p?->schedule_version ?? 1,
            'weekly' => DB::table('technician_availability')->where('technician_id', $user->id)->orderBy('weekday')->orderBy('starts_at')->get(),
            'exceptions' => DB::table('technician_availability_exceptions')->where('technician_id', $user->id)->orderBy('starts_at')->get(),
            'bookings' => Booking::where('technician_id', $user->id)->whereNotNull('scheduled_at')
                ->whereNotIn('status', ['cancelled', 'completed'])->orderBy('scheduled_at')
                ->get(['id', 'reference', 'service_category', 'status', 'scheduled_at', 'scheduled_end_at', 'travel_buffer_minutes']),
        ];
    }

    public function save(User $user, array $data): array
    {
        DB::transaction(function () use ($user, $data) {
            $p = TechnicianProfile::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            abort_unless($p->schedule_version === (int) $data['version'], 409, 'Schedule changed. Refresh before saving.');
            // Availability edits never cancel, move, or erase booked appointments.
            DB::table('technician_availability')->where('technician_id', $user->id)->delete();
            foreach ($data['weekly'] as $row) {
                DB::table('technician_availability')->insert($row + ['technician_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('technician_availability_exceptions')->where('technician_id', $user->id)->delete();
            foreach ($data['exceptions'] as $row) {
                $row['starts_at'] = CarbonImmutable::parse($row['starts_at'])->utc();
                $row['ends_at'] = CarbonImmutable::parse($row['ends_at'])->utc();
                DB::table('technician_availability_exceptions')->insert($row + ['technician_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $p->update(['schedule_timezone' => $data['timezone'], 'schedule_configured' => true, 'schedule_version' => $p->schedule_version + 1]);
            Audit::record('technician.schedule_updated', $p, ['version' => $p->schedule_version, 'schedule' => $data]);
        }, 3);
        $user->unsetRelation('technicianProfile');
        return $this->calendar($user);
    }

    public function assertBookable(User $technician, CarbonImmutable $start, int $minutes, int $buffer = 0, ?int $exceptBooking = null, bool $allowPast = false): void
    {
        // Call only inside a transaction. This serializes competing assignments to one technician.
        $technician = User::whereKey($technician->id)->lockForUpdate()->firstOrFail();
        $p = TechnicianProfile::where('user_id', $technician->id)->lockForUpdate()->firstOrFail();
        if (!$technician->is_active || !$p->is_active || !$p->is_available || $p->kyc_status !== 'verified') {
            throw ValidationException::withMessages(['scheduled_at' => 'The technician is not currently available for assignments.']);
        }
        $start = $start->utc();
        if (!$allowPast && $start->isPast()) throw ValidationException::withMessages(['scheduled_at' => 'Choose a future appointment.']);
        $end = $start->addMinutes($minutes);
        $reservedStart = $start->subMinutes($buffer);
        $reservedEnd = $end->addMinutes($buffer);
        $exceptions = DB::table('technician_availability_exceptions')->where('technician_id', $technician->id)
            ->where('starts_at', '<', $reservedEnd)->where('ends_at', '>', $reservedStart)->lockForUpdate()->get();
        if ($exceptions->contains(fn ($x) => !$x->available)) {
            throw ValidationException::withMessages(['scheduled_at' => 'This time is blocked or unavailable.']);
        }
        if ($p->schedule_configured) {
            $localStart = $reservedStart->setTimezone($p->schedule_timezone);
            $localEnd = $reservedEnd->setTimezone($p->schedule_timezone);
            $extra = $exceptions->contains(fn ($x) => $x->available && CarbonImmutable::parse($x->starts_at, 'UTC')->lte($reservedStart) && CarbonImmutable::parse($x->ends_at, 'UTC')->gte($reservedEnd));
            $weekly = $localStart->toDateString() === $localEnd->toDateString()
                && DB::table('technician_availability')->where('technician_id', $technician->id)->where('weekday', $localStart->dayOfWeek)
                    ->where('starts_at', '<=', $localStart->format('H:i:s'))->where('ends_at', '>=', $localEnd->format('H:i:s'))->lockForUpdate()->first();
            if (!$extra && !$weekly) throw ValidationException::withMessages(['scheduled_at' => 'This appointment is outside the technician’s working hours.']);
        }
        $bookings = Booking::where('technician_id', $technician->id)->whereNotNull('scheduled_at')
            ->whereNotIn('status', ['cancelled', 'completed'])->when($exceptBooking, fn ($q) => $q->where('id', '!=', $exceptBooking))->lockForUpdate()->get();
        foreach ($bookings as $booking) {
            $occupiedStart = CarbonImmutable::parse($booking->scheduled_at)->utc()->subMinutes($booking->travel_buffer_minutes);
            $occupiedEnd = $booking->scheduled_end_at
                ? CarbonImmutable::parse($booking->scheduled_end_at)->utc()
                : CarbonImmutable::parse($booking->scheduled_at)->utc()->addMinutes($booking->quotation?->duration_minutes ?? 60);
            $occupiedEnd = $occupiedEnd->addMinutes($booking->travel_buffer_minutes);
            if ($reservedStart->lt($occupiedEnd) && $reservedEnd->gt($occupiedStart)) {
                throw ValidationException::withMessages(['scheduled_at' => 'This appointment overlaps an existing booking or travel buffer.']);
            }
        }
    }

    public function resize(Booking $booking, int $minutes): void
    {
        if (!$booking->scheduled_at) return;
        // Diagnosis may finish after arrival. Preserve the appointment start while
        // checking that the quoted duration does not consume another reservation.
        $start = CarbonImmutable::parse($booking->scheduled_at)->utc();
        $this->assertBookable(User::findOrFail($booking->technician_id), $start, $minutes, $booking->travel_buffer_minutes, $booking->id, true);
        $booking->scheduled_end_at = $start->addMinutes($minutes);
    }

    public function book(Booking $booking, string $startsAt, int $minutes = 60, int $buffer = 15): void
    {
        $technician = User::findOrFail($booking->technician_id);
        $start = CarbonImmutable::parse($startsAt)->utc();
        $this->assertBookable($technician, $start, $minutes, $buffer, $booking->id);
        $booking->scheduled_at = $start;
        $booking->scheduled_end_at = $start->addMinutes($minutes);
        $booking->travel_buffer_minutes = $buffer;
    }
}
