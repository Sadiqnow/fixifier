<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Journey
{
    public static function participant(Booking $b, User $u, ?string $role = null): void
    {
        abort_unless($u->is_active && (!$role || $u->role->value === $role)
            && ($u->role->value === 'admin' || ($u->role->value === 'customer' && $b->customer_id === $u->id)
                || ($u->role->value === 'technician' && $b->technician_id === $u->id)), 403);
    }

    public static function round(Booking $b): void
    {
        DB::table('work_rounds')->insertOrIgnore(['booking_id' => $b->id, 'number' => $b->current_work_round,
            'technician_id' => $b->technician_id, 'scheduled_at' => $b->scheduled_at, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function updateRound(Booking $b, array $data): void
    {
        self::round($b);
        DB::table('work_rounds')->where('booking_id', $b->id)->where('number', $b->current_work_round)->update($data + ['updated_at' => now()]);
    }

    public static function record(Booking $b, string $type, string $body, array $metadata = []): void
    {
        DB::table('booking_updates')->insert(['booking_id' => $b->id, 'actor_id' => auth()->id(), 'work_round' => $b->current_work_round,
            'type' => $type, 'body' => $body, 'metadata' => json_encode($metadata), 'created_at' => now()]);
        self::notify($b, $type, $body);
    }

    public static function notify(Booking $b, string $type, string $body): void
    {
        $ids = [$b->customer_id, $b->technician_id];
        if (str_contains($type, 'dispute') || !$b->technician_id) $ids = array_merge($ids, User::where('role', 'admin')->where('is_active', true)->pluck('id')->all());
        foreach (array_unique(array_filter($ids)) as $id) {
            DB::table('journey_notifications')->insert(['user_id' => $id, 'booking_id' => $b->id, 'type' => $type, 'body' => $body, 'created_at' => now()]);
        }
    }
}
