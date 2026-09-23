<?php

namespace Tests\Feature;

use App\Models\Booking;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_is_repeatable_and_preserves_changes(): void
    {
        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $booking = Booking::where('reference', 'DEMO-FX-0001')->firstOrFail();
        $booking->update(['description' => 'Keep this manually edited booking description.']);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('users', 8);
        $this->assertDatabaseCount('bookings', 44);
        $this->assertDatabaseCount('audit_events', 44);
        $this->assertDatabaseCount('technician_profiles', 4);
        $this->assertSame('Keep this manually edited booking description.', $booking->fresh()->description);
        $this->assertDatabaseHas('technician_profiles', ['service_location' => 'Lagos', 'starting_price_minor' => 1000000]);
    }
}
