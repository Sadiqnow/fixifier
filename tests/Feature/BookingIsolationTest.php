<?php

namespace Tests\Feature;

use App\Models\{AuditEvent, Booking, Payment, Quotation, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::create(['name' => $role, 'email' => uniqid().'@example.test', 'password' => 'Password12345', 'role' => $role]);
    }

    private function booking(User $customer, User $technician, string $status = 'evidence_submitted'): Booking
    {
        $booking = Booking::create(['reference' => uniqid('FX-'), 'customer_id' => $customer->id, 'technician_id' => $technician->id, 'service_category' => 'Plumbing', 'description' => 'Repair a leaking kitchen tap.', 'address' => '10 Main Street', 'status' => $status]);
        Payment::create(['booking_id' => $booking->id, 'provider' => 'demo', 'provider_reference' => uniqid('DEMO-'), 'amount_minor' => 10000, 'currency' => 'NGN', 'status' => 'authorized', 'authorized_at' => now()]);
        return $booking;
    }

    public function test_approval_changes_only_booking_a_and_rejects_repeated_decisions(): void
    {
        $customer = $this->account('customer');
        $technician = $this->account('technician');
        $a = $this->booking($customer, $technician);
        $b = $this->booking($this->account('customer'), $this->account('technician'));
        $snapshot = $b->fresh()->getAttributes();
        $payment = $b->payment->getAttributes();
        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/bookings/{$a->id}/approve")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame($snapshot, $b->fresh()->getAttributes());
        $this->assertSame($payment, $b->payment()->first()->getAttributes());
        $this->assertDatabaseHas('payments', ['booking_id' => $a->id, 'status' => 'released']);
        $this->postJson("/api/v1/bookings/{$a->id}/approve")->assertConflict()->assertJsonPath('current_status', 'completed');
        $this->postJson("/api/v1/bookings/{$a->id}/dispute", $this->disputeData())->assertConflict()->assertJsonPath('current_status', 'completed');
        $this->assertDatabaseCount('audit_events', 1);
        $this->assertDatabaseCount('disputes', 0);
    }

    private function disputeData(): array
    {
        return ['reason' => 'Incomplete repair', 'details' => 'The kitchen tap continues to leak after the repair.'];
    }

    public function test_invalid_states_return_consistent_conflicts_without_any_changes(): void
    {
        $customer = $this->account('customer');
        $technician = $this->account('technician');
        Sanctum::actingAs($customer);
        foreach (['requested', 'quoted', 'confirmed', 'in_progress', 'completed', 'disputed', 'cancelled'] as $status) {
            $booking = $this->booking($customer, $technician, $status);
            $snapshot = $booking->fresh()->getAttributes();
            $payment = $booking->payment->getAttributes();
            foreach (['approve', 'dispute'] as $action) {
                $this->postJson("/api/v1/bookings/{$booking->id}/{$action}", $action === 'dispute' ? $this->disputeData() : [])
                    ->assertConflict()->assertJsonStructure(['message', 'code', 'current_status'])
                    ->assertJsonPath('code', 'invalid_transition')->assertJsonPath('current_status', $status);
            }
            $this->assertSame($snapshot, $booking->fresh()->getAttributes());
            $this->assertSame($payment, $booking->payment()->first()->getAttributes());
        }
        $this->assertDatabaseCount('disputes', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_forbidden_quote_acceptance_does_not_disclose_state_or_accept_quote(): void
    {
        $customer = $this->account('customer');
        $technician = $this->account('technician');
        $booking = $this->booking($customer, $technician, 'quoted');
        $quote = Quotation::create(['booking_id' => $booking->id, 'amount_minor' => 10000, 'currency' => 'NGN', 'scope' => 'Repair the tap', 'expires_at' => now()->addDay()]);
        foreach ([$this->account('customer'), $technician, $this->account('admin')] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/bookings/{$booking->id}/quotation/accept")
                ->assertForbidden()->assertJsonMissingPath('current_status');
        }
        $this->assertNull($quote->fresh()->accepted_at);
        $this->assertSame('quoted', $booking->fresh()->status->value);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_dispute_changes_only_booking_a_and_rejects_repeated_decisions(): void
    {
        $customer = $this->account('customer');
        $technician = $this->account('technician');
        $a = $this->booking($customer, $technician);
        $b = $this->booking($this->account('customer'), $this->account('technician'));
        $snapshot = $b->fresh()->getAttributes();
        $payment = $b->payment->getAttributes();
        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/bookings/{$a->id}/dispute", $this->disputeData())->assertCreated();
        $this->assertSame('disputed', $a->fresh()->status->value);
        $this->assertSame($snapshot, $b->fresh()->getAttributes());
        $this->assertSame($payment, $b->payment()->first()->getAttributes());
        $this->assertDatabaseHas('payments', ['booking_id' => $a->id, 'status' => 'authorized']);
        $this->postJson("/api/v1/bookings/{$a->id}/dispute", $this->disputeData())->assertConflict()->assertJsonPath('current_status', 'disputed');
        $this->postJson("/api/v1/bookings/{$a->id}/approve")->assertConflict()->assertJsonPath('current_status', 'disputed');
        $this->assertDatabaseCount('disputes', 1);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_non_owner_customer_technician_and_admin_cannot_make_customer_decisions(): void
    {
        $customer = $this->account('customer');
        $technician = $this->account('technician');
        $booking = $this->booking($customer, $technician);
        foreach ([$this->account('customer'), $technician, $this->account('admin')] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson("/api/v1/bookings/{$booking->id}/approve")->assertForbidden();
            $this->postJson("/api/v1/bookings/{$booking->id}/dispute", $this->disputeData())->assertForbidden();
        }
        $this->assertSame('evidence_submitted', $booking->fresh()->status->value);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseCount('disputes', 0);
    }

    public function test_quote_expiry_is_enforced_and_acceptance_is_not_repeatable(): void
    {
        $this->freezeTime();
        $customer = $this->account('customer');
        $booking = $this->booking($customer, $this->account('technician'), 'quoted');
        $quote = Quotation::create(['booking_id' => $booking->id, 'amount_minor' => 10000, 'currency' => 'NGN', 'scope' => 'Repair the tap', 'expires_at' => now()]);
        Sanctum::actingAs($customer);
        $url = "/api/v1/bookings/{$booking->id}/quotation/accept";
        $this->postJson($url)->assertConflict()->assertJsonPath('code', 'quote_expired')->assertJsonPath('current_status', 'quoted');
        $this->assertNull($quote->fresh()->accepted_at);
        $this->assertDatabaseCount('audit_events', 0);
        $quote->update(['expires_at' => now()->addHour()]);
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->postJson($url)->assertConflict()->assertJsonPath('current_status', 'confirmed');
        $this->assertNotNull($quote->fresh()->accepted_at);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_audit_failure_rolls_back_approval_and_payment(): void
    {
        $customer = $this->account('customer');
        $booking = $this->booking($customer, $this->account('technician'));
        Sanctum::actingAs($customer);
        AuditEvent::creating(function () { throw new \RuntimeException('Simulated audit failure'); });
        try {
            $this->postJson("/api/v1/bookings/{$booking->id}/approve")->assertStatus(500);
            $this->assertSame('evidence_submitted', $booking->fresh()->status->value);
            $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'status' => 'authorized', 'released_at' => null]);
        } finally {
            AuditEvent::flushEventListeners();
        }
    }

    public function test_stale_route_bound_booking_is_reloaded_before_a_decision(): void
    {
        $customer = $this->account('customer');
        $booking = $this->booking($customer, $this->account('technician'));
        $staleBooking = $booking->fresh();
        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/bookings/{$booking->id}/approve")->assertOk();
        $request = \Illuminate\Http\Request::create('/dispute', 'POST', $this->disputeData());
        $request->setUserResolver(fn () => $customer);
        try {
            app(\App\Http\Controllers\Api\V1\WorkflowController::class)->dispute($request, $staleBooking);
            $this->fail('A stale bound booking must not bypass the locked state check.');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
            $this->assertSame('completed', $exception->getResponse()->getData(true)['current_status']);
        }
        $this->assertDatabaseCount('disputes', 0);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_audit_failure_rolls_back_dispute_and_booking(): void
    {
        $customer = $this->account('customer');
        $booking = $this->booking($customer, $this->account('technician'));
        Sanctum::actingAs($customer);
        AuditEvent::creating(function () { throw new \RuntimeException('Simulated audit failure'); });
        try {
            $this->postJson("/api/v1/bookings/{$booking->id}/dispute", $this->disputeData())->assertStatus(500);
            $this->assertSame('evidence_submitted', $booking->fresh()->status->value);
            $this->assertDatabaseCount('disputes', 0);
            $this->assertDatabaseCount('audit_events', 0);
            $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'status' => 'authorized']);
        } finally {
            AuditEvent::flushEventListeners();
        }
    }
}

