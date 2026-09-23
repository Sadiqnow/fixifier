<?php

namespace Tests\Feature;

use App\Models\{Booking, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerificationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_shell_uses_live_client_without_embedded_sample_job(): void
    {
        $this->get('/job-verification?booking=1')->assertOk()->assertSee('js/job-verification.js')
            ->assertDontSee('FX-2026-0148')->assertDontSee('localStorage')->assertSee('id="picker"', false);
    }

    public function test_owner_review_is_persisted_and_unrelated_customer_cannot_read_or_change_booking(): void
    {
        $customer = User::create(['name'=>'Customer', 'email'=>'owner@example.test', 'password'=>'Password12345', 'role'=>'customer']);
        $other = User::create(['name'=>'Other', 'email'=>'other@example.test', 'password'=>'Password12345', 'role'=>'customer']);
        $booking = Booking::create(['reference'=>'VERIFY-1', 'customer_id'=>$customer->id, 'service_category'=>'Plumbing', 'description'=>'Repair the leaking kitchen tap.', 'address'=>'10 Test Street', 'status'=>'evidence_submitted']);
        $this->getJson('/api/v1/bookings/'.$booking->id)->assertUnauthorized();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/bookings/'.$booking->id)->assertForbidden();
        $this->postJson('/api/v1/bookings/'.$booking->id.'/approve')->assertForbidden();
        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/bookings/'.$booking->id)->assertOk()->assertJsonPath('data.status','evidence_submitted');
        $this->postJson('/api/v1/bookings/'.$booking->id.'/approve')->assertOk();
        $this->getJson('/api/v1/bookings/'.$booking->id)->assertOk()->assertJsonPath('data.status','completed');
    }
}
