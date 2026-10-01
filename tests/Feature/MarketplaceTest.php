<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Category;
use App\Models\JobEvidence;
use App\Models\ServiceArea;
use App\Models\TechnicianProfile;
use App\Models\User;
use Database\Seeders\AdminMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => uniqid().'@example.com', 'password' => 'Password12345', 'role' => $role]);
    }

    public function test_admin_dashboard_data_requires_an_administrator(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->getJson('/api/v1/admin/dashboard?section=overview')->assertUnauthorized();
        Sanctum::actingAs($this->account('customer'));
        $this->getJson('/api/v1/admin/dashboard?section=customers')->assertForbidden();
        Sanctum::actingAs($this->account('technician'));
        $this->getJson('/api/v1/admin/dashboard?section=customers')->assertForbidden();
        Sanctum::actingAs($this->account('admin'));
        foreach (['overview', 'technicians', 'bookings', 'evidence', 'disputes', 'customers', 'finance', 'audit'] as $section) {
            $this->getJson('/api/v1/admin/dashboard?section='.$section)->assertOk()->assertJsonStructure(['summary', 'records' => ['data', 'current_page', 'last_page']]);
        }
    }

    public function test_home_serves_the_marketplace_and_portal_requires_authentication(): void
    {
        $this->get('/')->assertOk()->assertSee('Every repair.')->assertSee('One trusted place.')->assertSee(route('booking.create').'?service=electrical', false)->assertSee(route('login'), false);
        $this->get(route('login'))->assertOk()->assertSee('authForm');
        $this->get(route('booking.create', ['service' => 'electrical']))->assertOk()->assertSee('name="booking-intent" content="true"', false);
        $this->get('/portal')->assertOk()->assertSee('Welcome back')->assertSee('js/marketplace.js');
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/technicians')->assertUnauthorized();
    }

    public function test_directory_only_returns_technicians_and_booking_rejects_other_roles(): void
    {
        $customer = $this->account('customer');
        $technician = $this->account('technician');
        TechnicianProfile::create(['user_id' => $technician->id, 'trade' => 'Plumbing', 'service_location' => 'Lagos', 'kyc_status' => 'verified']);
        $this->seed(AdminMarketplaceSeeder::class);
        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/me')->assertJsonPath('data.id', $customer->id);
        $this->getJson('/api/v1/technicians')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $technician->id)->assertJsonMissingPath('data.0.email');
        $data = ['technician_id' => $customer->id, 'service_category' => 'Plumbing', 'description' => 'The kitchen tap has been leaking for several days.', 'address' => '10 Main Street'];
        $this->postJson('/api/v1/bookings', $data)->assertUnprocessable()->assertJsonValidationErrors('technician_id');
        $data['technician_id'] = $technician->id;
        $this->postJson('/api/v1/bookings', $data)->assertCreated()->assertJsonPath('data.status', 'requested');
    }

    public function test_technician_profile_update_persists_trade_service_area_and_availability(): void
    {
        Category::create(['name' => 'Electrical', 'normalized_name' => 'electrical', 'active' => true]);
        ServiceArea::create(['name' => 'Lagos', 'normalized_name' => 'lagos', 'active' => true]);

        $technician = $this->account('technician');
        Sanctum::actingAs($technician);

        $payload = [
            'trade' => 'Electrical',
            'service_location' => 'Lagos',
            'bio' => 'Qualified electrician with over 10 years of repair and installation work.',
            'skills' => 'Wiring, panel upgrades, lighting, fault finding, appliance installation',
            'years_experience' => 10,
            'indicative_price_minor' => 250000,
            'is_available' => true,
            'availability_notes' => 'Available evenings and Saturdays.',
        ];

        $this->putJson('/api/v1/technician/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.trade', 'Electrical')
            ->assertJsonPath('data.service_location', 'Lagos')
            ->assertJsonPath('data.is_available', true);

        $this->assertDatabaseHas('technician_profiles', ['user_id' => $technician->id, 'trade' => 'Electrical', 'service_location' => 'Lagos', 'is_available' => true]);
    }

    public function test_technician_profile_update_accepts_legacy_profile_field_names(): void
    {
        Category::create(['name' => 'Electrical', 'normalized_name' => 'electrical', 'active' => true]);
        ServiceArea::create(['name' => 'Lagos', 'normalized_name' => 'lagos', 'active' => true]);

        $technician = $this->account('technician');
        Sanctum::actingAs($technician);

        $payload = [
            'trade' => 'Electrical',
            'service_area' => 'Lagos',
            'bio' => 'Qualified electrician with over 10 years of repair and installation work.',
            'specialties' => 'Wiring, panel upgrades, lighting, fault finding, appliance installation',
            'experience_years' => 10,
            'starting_price_minor' => 180000,
            'is_available' => false,
            'availability_notes' => 'Weekday afternoons only.',
        ];

        $this->putJson('/api/v1/technician/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.trade', 'Electrical')
            ->assertJsonPath('data.service_location', 'Lagos')
            ->assertJsonPath('data.is_available', false);

        $this->assertDatabaseHas('technician_profiles', ['user_id' => $technician->id, 'trade' => 'Electrical', 'service_location' => 'Lagos', 'is_available' => false, 'starting_price_minor' => 180000]);
    }

    public function test_evidence_is_only_visible_to_booking_participants_and_administrators(): void
    {
        Storage::fake('private');
        $customer = $this->account('customer');
        $technician = $this->account('technician');
        $other = $this->account('customer');
        $admin = $this->account('admin');
        $booking = Booking::create(['reference' => 'FX-TEST', 'customer_id' => $customer->id, 'technician_id' => $technician->id, 'service_category' => 'Plumbing', 'description' => 'A leaking kitchen tap needs repair.', 'address' => '10 Main Street', 'status' => 'in_progress']);
        Storage::disk('private')->put('test/photo.jpg', 'test-image');
        $evidence = JobEvidence::create(['booking_id' => $booking->id, 'technician_id' => $technician->id, 'type' => 'before', 'storage_path' => 'test/photo.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 10, 'sha256' => hash('sha256', 'test-image'), 'captured_at' => now()]);
        $url = '/api/v1/evidence/'.$evidence->id;
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs($other);
        $this->getJson($url)->assertForbidden();
        foreach ([$customer, $technician, $admin] as $account) {
            Sanctum::actingAs($account);
            $this->getJson($url)->assertOk();
        }
    }
}
