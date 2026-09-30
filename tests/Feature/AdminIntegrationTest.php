<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Category;
use App\Models\KycDocument;
use App\Models\MarketplaceSetting;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Review;
use App\Models\TechnicianProfile;
use App\Models\User;
use Database\Seeders\AdminMarketplaceSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role = 'admin', bool $active = true): User
    {
        return User::create(['name' => ucfirst($role), 'email' => uniqid().'@example.test', 'password' => 'TestPassword123!', 'role' => $role, 'is_active' => $active]);
    }

    private function technician(): User
    {
        $tech = $this->account('technician');
        TechnicianProfile::create(['user_id' => $tech->id, 'trade' => 'Plumbing', 'service_location' => 'Lagos', 'kyc_status' => 'verified']);
        $this->seed(AdminMarketplaceSeeder::class);

        return $tech;
    }

    private function booking(?User $customer = null, ?User $tech = null, string $status = 'requested'): Booking
    {
        return Booking::create(['reference' => uniqid('FX-'), 'customer_id' => ($customer ?? $this->account('customer'))->id,
            'technician_id' => $tech?->id, 'service_category' => 'Plumbing', 'service_area' => 'Lagos',
            'description' => 'Repair a kitchen tap that keeps leaking.', 'address' => '10 Main Street, Lagos', 'status' => $status]);
    }

    public function test_session_login_is_admin_only_and_logout_closes_access(): void
    {
        $admin = $this->account();
        $customer = $this->account('customer');
        $disabled = $this->account('admin', false);
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('Admin sign in');
        foreach ([$customer, $disabled] as $user) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'TestPassword123!'])->assertSessionHasErrors('email');
            $this->assertGuest('web');
        }
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'TestPassword123!'])->assertRedirect('/admin/overview');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest('web');
        $this->get('/admin/setup')->assertRedirect('/admin/login');
        $this->get('/login')->assertOk()->assertSee('authForm');
    }

    public function test_login_is_rate_limited_and_web_writes_require_csrf(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'invalid@example.test', 'password' => 'bad'])->assertStatus(302);
        }
        $this->post('/admin/login', ['email' => 'invalid@example.test', 'password' => 'bad'])->assertStatus(429);
        $this->app['env'] = 'production';
        $this->post('/admin/login', ['email' => 'invalid@example.test', 'password' => 'bad'])->assertStatus(419);
    }

    public function test_all_pages_render_empty_and_populated_with_existing_data(): void
    {
        Storage::fake('private');
        $this->seed(AdminMarketplaceSeeder::class);
        $this->actingAs($this->account(), 'web');
        $this->pages();
        $this->seed(DatabaseSeeder::class);
        $this->pages();
        $this->get('/admin/jobs')->assertSee('DEMO-FX-');
        $this->get('/admin')->assertSee('assets/css/admin.css')->assertDontSee('Simulate callback');
        $this->get('/admin/earnings/calculation?amount=22000')->assertOk()->assertSee('Technician net');
    }

    private function pages(): void
    {
        $this->get('/admin')->assertOk();
        foreach (array_keys(config('fixifier.navigation')) as $page) {
            $response = $this->get('/admin/'.$page);
            if ($page === 'overview') {
                $response->assertRedirect('/admin');
            } else {
                $response->assertOk()->assertSee(config('fixifier.navigation.'.$page)[1]);
            }
        }
    }

    public function test_non_admin_and_disabled_admin_cannot_read_or_mutate_admin_records(): void
    {
        $this->seed(AdminMarketplaceSeeder::class);
        foreach ([$this->account('customer'), $this->account('technician'), $this->account('admin', false)] as $user) {
            $this->actingAs($user, 'web');
            $this->get('/admin')->assertForbidden();
            $this->post('/admin/catalog/categories', ['name' => 'Electrical'])->assertForbidden();
        }
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_catalog_settings_and_verification_are_persistent_versioned_and_audited(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('kyc/id.pdf', 'private identity record');
        $tech = $this->technician();
        $this->actingAs($this->account(), 'web');
        $this->post('/admin/catalog/categories', ['name' => 'Electrical'])->assertSessionHasNoErrors();
        $this->post('/admin/catalog/categories', ['name' => ' electrical '])->assertSessionHasErrors('name');
        $category = Category::where('name', 'Electrical')->firstOrFail();
        $this->post('/admin/catalog/categories/'.$category->id, ['active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($category->fresh()->active);
        $ranking = ['version' => 1, 'ratingWeight' => 40, 'distanceWeight' => 20, 'availabilityWeight' => 20, 'completionWeight' => 20];
        $this->post('/admin/settings/ranking', $ranking)->assertSessionHasNoErrors();
        $this->assertSame(2, MarketplaceSetting::find(1)->version);
        $this->post('/admin/settings/ranking', $ranking)->assertConflict();
        $ranking['version'] = 2;
        $ranking['ratingWeight'] = 90;
        $this->post('/admin/settings/ranking', $ranking)->assertSessionHasErrors('ratingWeight');
        $profile = $tech->technicianProfile;
        $url = '/admin/technicians/'.$tech->id.'/decision';
        $this->post($url, ['version' => 1, 'decision' => 'suspended', 'reason' => 'Identity document requires another review.'])->assertSessionHasNoErrors();
        $this->assertFalse($profile->fresh()->is_active);
        $this->post($url, ['version' => 1, 'decision' => 'approved', 'reason' => 'Documents checked and approved.'])->assertConflict();
        $this->post($url, ['version' => 2, 'decision' => 'approved', 'reason' => 'Documents checked and approved.'])->assertSessionHasErrors('decision');
        KycDocument::create(['user_id' => $tech->id, 'type' => 'identity', 'storage_path' => 'kyc/id.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64)]);
        $this->post($url, ['version' => 2, 'decision' => 'approved', 'reason' => 'Documents checked and approved.'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('technician_decisions', 2);
        $this->assertSame('verified', $profile->fresh()->kyc_status);
        $this->assertTrue($profile->fresh()->is_active);
        $this->assertDatabaseHas('audit_events', ['action' => 'technician.verification_decided', 'subject_id' => $profile->id]);
    }

    public function test_assignment_enforces_coverage_eligibility_and_stale_versions(): void
    {
        $tech = $this->technician();
        $booking = $this->booking();
        $untouched = $this->booking();
        $snapshot = $untouched->fresh()->getAttributes();
        $this->actingAs($this->account(), 'web');
        $data = ['version' => 1, 'technician_id' => $tech->id, 'service_area' => 'Abuja', 'reason' => 'Technician is available for this repair.'];
        $url = '/admin/bookings/'.$booking->id.'/assign';
        $this->post($url, $data)->assertSessionHasErrors('technician_id');
        $data['service_area'] = 'Lagos';
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->assertSame($tech->id, $booking->fresh()->technician_id);
        $this->post($url, $data)->assertConflict();
        $this->post('/admin/bookings/'.$booking->id.'/requeue', ['version' => 2, 'reason' => 'Technician requested reassignment for availability.'])->assertSessionHasNoErrors();
        $this->assertNull($booking->fresh()->technician_id);
        $tech->technicianProfile->update(['is_active' => false]);
        $data['version'] = 3;
        $this->post($url, $data)->assertSessionHasErrors('technician_id');
        $this->assertSame($snapshot, $untouched->fresh()->getAttributes());
        $this->post('/admin/bookings/'.$booking->id.'/close', ['version' => 3, 'reason' => 'Customer withdrew the unpaid service request.'])->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_disabling_a_category_blocks_directory_and_new_booking_but_preserves_existing_jobs(): void
    {
        $tech = $this->technician();
        $customer = $this->account('customer');
        $booking = $this->booking($customer, $tech, 'confirmed');
        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/technicians')->assertJsonCount(1, 'data');
        Category::where('name', 'Plumbing')->update(['active' => false]);
        $this->getJson('/api/v1/technicians')->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/bookings', ['technician_id' => $tech->id, 'service_category' => 'Plumbing', 'description' => 'A leaking kitchen tap needs to be repaired.', 'address' => 'Lagos'])->assertUnprocessable();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_financial_rules_reject_deductions_larger_than_existing_quotes(): void
    {
        $this->seed(AdminMarketplaceSeeder::class);
        $booking = $this->booking();
        Quotation::create(['booking_id' => $booking->id, 'amount_minor' => 10000, 'currency' => 'NGN', 'scope' => 'Repair tap', 'expires_at' => now()->addDay()]);
        $this->actingAs($this->account(), 'web');
        $data = ['version' => 1, 'feePercent' => 90, 'feeFixed' => 1000, 'providerFeePercent' => 0, 'providerFeeFixed' => 0, 'providerFeePayer' => 'platform', 'reservePercent' => 0, 'payoutBatch' => 'manual', 'minPayout' => 0];
        $this->post('/admin/settings/finance', $data)->assertSessionHasErrors('feePercent');
        $this->assertSame(1, MarketplaceSetting::find(1)->version);
    }

    private function upload(Booking $booking, string $phase, int $round)
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jf6kAAAAASUVORK5CYII=');

        return $this->postJson('/api/v1/bookings/'.$booking->id.'/evidence', ['type' => $phase, 'expected_work_round' => $round,
            'captured_at' => now()->subMinute()->toISOString(), 'photo' => UploadedFile::fake()->createWithContent('repair.png', $png)]);
    }

    public function test_two_dispute_rounds_require_fresh_evidence_and_never_fake_a_transfer(): void
    {
        Storage::fake('private');
        $tech = $this->technician();
        $customer = $this->account('customer');
        $admin = $this->account();
        $booking = $this->booking($customer, $tech, 'confirmed');
        $other = $this->booking();
        $snapshot = $other->fresh()->getAttributes();
        Payment::create(['booking_id' => $booking->id, 'provider' => 'test-provider', 'provider_reference' => uniqid(),
            'amount_minor' => 10000, 'currency' => 'NGN', 'status' => 'authorized', 'authorized_at' => now()]);
        $base = '/api/v1/bookings/'.$booking->id;
        for ($round = 1; $round <= 2; $round++) {
            Sanctum::actingAs($tech);
            if ($round === 2) {
                $this->postJson($base.'/start', ['expected_work_round' => 1])->assertConflict();
            }
            $this->postJson($base.'/start', ['expected_work_round' => $round])->assertUnprocessable();
            $this->upload($booking, 'before', $round)->assertCreated()->assertJsonPath('data.work_round', $round);
            $this->postJson($base.'/start', ['expected_work_round' => $round])->assertOk();
            $this->postJson($base.'/evidence/submit', ['expected_work_round' => $round])->assertUnprocessable();
            $this->upload($booking, 'after', $round)->assertCreated();
            $this->postJson($base.'/evidence/submit', ['expected_work_round' => $round])->assertOk();
            Sanctum::actingAs($customer);
            if ($round === 2) {
                $this->postJson($base.'/approve', ['expected_work_round' => 1])->assertConflict();
            }
            $this->postJson($base.'/dispute', ['reason' => 'Issue persists', 'details' => 'The repaired tap is still leaking after the work.', 'expected_work_round' => $round])->assertCreated();
            Sanctum::actingAs($admin);
            $this->actingAs($admin, 'web');
            $this->post('/admin/bookings/'.$booking->id.'/dispute/resolve', [
                'version' => $booking->fresh()->lock_version, 'decision' => $round === 1 ? 'rework_required' : 'release_pending',
                'reason' => $round === 1 ? 'The submitted evidence requires a fresh repair round.' : 'The repaired tap meets the agreed scope after review.',
            ])->assertSessionHasNoErrors();
        }
        $booking->refresh();
        $this->assertSame(2, $booking->current_work_round);
        $this->assertSame(BookingStatus::Completed, $booking->status);
        $this->assertSame('release_pending', $booking->settlement_status);
        $this->assertSame('release_pending', $booking->payment->status);
        $this->assertNull($booking->payment->released_at);
        $this->assertDatabaseCount('disputes', 2);
        $this->assertDatabaseCount('job_evidence', 4);
        $this->assertSame('rework', $booking->disputes()->where('work_round', 1)->first()->decision);
        $this->assertSame($snapshot, $other->fresh()->getAttributes());
    }

    public function test_private_documents_evidence_and_review_notes_require_admin_session(): void
    {
        Storage::fake('private');
        $tech = $this->technician();
        $booking = $this->booking(null, $tech);
        Storage::disk('private')->put('kyc/id.pdf', 'private document');
        $doc = KycDocument::create(['user_id' => $tech->id, 'type' => 'identity', 'storage_path' => 'kyc/id.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 16, 'sha256' => str_repeat('b', 64)]);
        $url = '/admin/private/documents/'.$doc->id;
        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs($this->account('customer'), 'web')->get($url)->assertForbidden();
        $this->actingAs($this->account(), 'web')->get($url)->assertOk()->assertDownload('id.pdf');
        $this->post('/admin/bookings/'.$booking->id.'/evidence/flag', ['version' => 1, 'reason' => 'Please provide a clearer image of the repaired tap.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('evidence_flags', ['booking_id' => $booking->id, 'work_round' => 1]);
        $this->post('/admin/bookings/'.$booking->id.'/evidence/flag', ['version' => 1, 'reason' => 'An outdated form must not be accepted again.'])->assertConflict();
    }

    public function test_only_booking_customer_can_rate_once_and_moderation_preserves_original_content(): void
    {
        $tech = $this->technician();
        $customer = $this->account('customer');
        $booking = $this->booking($customer, $tech, 'completed');
        $url = '/api/v1/bookings/'.$booking->id.'/rating';
        $data = ['stars' => 4, 'comment' => 'The technician repaired the leak successfully.'];
        Sanctum::actingAs($this->account('customer'));
        $this->postJson($url, $data)->assertForbidden();
        Sanctum::actingAs($customer);
        $this->postJson($url, $data)->assertCreated();
        $this->postJson($url, $data)->assertConflict();
        $review = Review::firstOrFail();
        $this->actingAs($this->account(), 'web');
        $this->post('/admin/reviews/'.$review->id.'/moderate', ['version' => 1, 'reason' => 'Temporarily hidden while resolving a content complaint.'])->assertSessionHasNoErrors();
        $this->assertSame('hidden', $review->fresh()->status);
        $this->assertSame($data['comment'], $review->fresh()->comment);
        $this->assertSame(4, $review->fresh()->stars);
        $this->get('/admin/ratings')->assertOk()->assertSee($data['comment']);
    }

    public function test_audit_failure_rolls_back_assignment(): void
    {
        $tech = $this->technician();
        $booking = $this->booking();
        $this->actingAs($this->account(), 'web');
        AuditEvent::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->post('/admin/bookings/'.$booking->id.'/assign', ['version' => 1, 'technician_id' => $tech->id, 'service_area' => 'Lagos', 'reason' => 'Eligible technician in the right area.'])->assertStatus(500);
            $this->assertNull($booking->fresh()->technician_id);
            $this->assertSame(1, $booking->fresh()->lock_version);
        } finally {
            AuditEvent::flushEventListeners();
        }
    }

    public function test_additive_migration_preserves_existing_core_records(): void
    {
        $migration = require database_path('migrations/2026_09_30_000001_add_admin_marketplace.php');
        $migration->down();
        $id = DB::table('users')->insertGetId(['name' => 'Existing customer', 'email' => 'existing@example.test', 'password' => 'old-hash', 'role' => 'customer']);
        $booking = DB::table('bookings')->insertGetId(['reference' => 'LEGACY-001', 'customer_id' => $id, 'service_category' => 'Plumbing', 'description' => 'Keep this old description', 'address' => 'Lagos', 'status' => 'confirmed', 'current_work_round' => 1]);
        $migration->up();
        $this->assertDatabaseHas('users', ['id' => $id, 'email' => 'existing@example.test', 'password' => 'old-hash', 'is_active' => true]);
        $this->assertDatabaseHas('bookings', ['id' => $booking, 'reference' => 'LEGACY-001', 'description' => 'Keep this old description', 'status' => 'confirmed', 'lock_version' => 1, 'settlement_status' => null]);
        $this->seed(AdminMarketplaceSeeder::class);
        $settings = MarketplaceSetting::findOrFail(1);
        $values = $settings->values;
        $values['name'] = 'Preserve admin choice';
        $settings->update(['values' => $values]);
        $this->seed(AdminMarketplaceSeeder::class);
        $this->assertSame('Preserve admin choice', MarketplaceSetting::find(1)->values['name']);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_admin_session_does_not_replace_customer_bearer_identity(): void
    {
        $admin = $this->account();
        $customer = $this->account('customer');
        $token = $customer->createToken('test-client')->plainTextToken;
        $this->actingAs($admin, 'web');
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $customer->id);
    }

    public function test_legacy_in_progress_job_can_supply_missing_before_evidence(): void
    {
        Storage::fake('private');
        $tech = $this->technician();
        $booking = $this->booking(null,$tech,'in_progress');
        Sanctum::actingAs($tech);
        $this->upload($booking,'before',1)->assertCreated();
        $this->upload($booking,'after',1)->assertCreated();
        $this->postJson('/api/v1/bookings/'.$booking->id.'/evidence/submit',['expected_work_round' => 1])->assertOk();
    }
}
