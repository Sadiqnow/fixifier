<?php

namespace Tests\Feature;

use App\Models\{Booking, Review, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicianDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_full_history_without_exposing_another_technicians_records(): void
    {
        $tech = User::create(['name'=>'Technician','email'=>'tech@example.test','password'=>'Password12345','role'=>'technician']);
        $other = User::create(['name'=>'Other','email'=>'other@example.test','password'=>'Password12345','role'=>'technician']);
        $customer = User::create(['name'=>'Customer','email'=>'customer@example.test','password'=>'Password12345','role'=>'customer']);
        for ($n = 1; $n <= 31; $n++) {
            $job = Booking::create(['reference'=>'DASH-'.$n,'customer_id'=>$customer->id,'technician_id'=>$n === 31 ? $other->id : $tech->id,'service_category'=>'Plumbing','description'=>'Repair the tap','address'=>'Private address','status'=>$n === 1 ? 'completed' : 'requested','scheduled_at'=>now()->addDays($n)]);
            if ($n === 1) Review::create(['booking_id'=>$job->id,'customer_id'=>$customer->id,'technician_id'=>$tech->id,'stars'=>4,'comment'=>'Customer feedback','status'=>'published']);
        }
        Sanctum::actingAs($tech);
        $response = $this->getJson('/api/v1/technician/dashboard')->assertOk()->assertJsonPath('data.total_bookings',30)
            ->assertJsonPath('data.active_jobs',29)->assertJsonPath('data.completed_jobs',1)->assertJsonPath('data.rating_count',1)
            ->assertJsonPath('data.rating_average',4)->assertJsonCount(5,'data.upcoming');
        $this->assertStringNotContainsString('DASH-31', $response->getContent());
        $this->assertStringNotContainsString('Private address', $response->getContent());
        Review::query()->update(['status'=>'hidden']);
        $this->getJson('/api/v1/technician/dashboard')->assertOk()->assertJsonPath('data.rating_count',0)->assertJsonPath('data.rating_average',null)->assertJsonCount(0,'data.recent_reviews');
        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/technician/dashboard')->assertForbidden();
    }
}
