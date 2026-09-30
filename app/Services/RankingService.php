<?php

namespace App\Services;

use App\Support\AdminBooking;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

class RankingService
{
    private ?Collection $candidates = null;

    public function eligible(AdminBooking $booking, array $settings): Collection
    {
        $users = $this->candidates ??= app(EligibilityService::class)->query()->with(['technicianProfile.decisions', 'reviews', 'assignedBookings', 'documents'])->get();

        return $users->filter(fn ($u) => Str::lower(trim($u->technicianProfile->trade)) === Str::lower(trim($booking->category->name))
                && (! $booking->service_area_id || Str::lower(trim($u->technicianProfile->service_location)) === Str::lower(trim($booking->service_area_id))))
            ->map(fn ($u) => app(AdminPortalData::class)->technician($u))
            ->sortByDesc(fn ($t) => $this->score($t, $booking->service_area_id, $settings));
    }

    public function score(Fluent $t, string $area, array $s): int
    {
        $rating = $t->rating_count ? $t->rating / 5 * 100 : 50;
        $sameArea = $area && Str::lower(trim($t->service_area_id)) === Str::lower(trim($area));

        return (int) round(($rating * $s['ratingWeight'] + ($sameArea ? 100 : 0) * $s['distanceWeight']
            + ($t->available ? 100 : 0) * $s['availabilityWeight'] + min($t->completed / 50 * 100, 100) * $s['completionWeight']) / 100);
    }
}
