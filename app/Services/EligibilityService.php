<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EligibilityService
{
    public function query(): Builder
    {
        return User::where('role', 'technician')->where('is_active', true)
            ->whereHas('technicianProfile', function ($q) {
                $q->where('kyc_status', 'verified')->where('is_active', true)->where('is_available', true)
                    ->whereIn(DB::raw('LOWER(TRIM(trade))'), Category::where('active', true)->select('normalized_name'))
                    ->whereIn(DB::raw('LOWER(TRIM(service_location))'), ServiceArea::where('active', true)->select('normalized_name'));
            });
    }

    public function matches(User $user, string $category, ?string $area = null): bool
    {
        $profile = $user->technicianProfile;

        return $profile && $this->query()->whereKey($user->id)->exists()
            && Str::lower(trim($profile->trade)) === Str::lower(trim($category))
            && (! $area || Str::lower(trim($profile->service_location)) === Str::lower(trim($area)));
    }
}
