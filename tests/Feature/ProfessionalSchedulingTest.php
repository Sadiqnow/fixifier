<?php

namespace Tests\Feature;

use App\Models\{Booking, TechnicianProfile, User};
use App\Services\TechnicianAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfessionalSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function technician(): User
    {
        $user = User::create(['name'=>'Professional','email'=>'tech@example.test','password'=>'Password12345','role'=>'technician']);
        TechnicianProfile::create(['user_id'=>$user->id,'trade'=>'Plumbing','kyc_status'=>'verified','is_active'=>true,'is_available'=>true]);
        return $user;
    }

    public function test_schedule_is_owned_persisted_versioned_and_rejects_invalid_windows(): void
    {
        $tech=$this->technician();
        Sanctum::actingAs($tech);
        $payload=['version'=>1,'timezone'=>'Africa/Lagos','weekly'=>[['weekday'=>1,'starts_at'=>'08:00','ends_at'=>'18:00']],'exceptions'=>[]];
        $this->putJson('/api/v1/technician/schedule',$payload)->assertOk()->assertJsonPath('data.version',2);
        $this->getJson('/api/v1/technician/schedule')->assertOk()->assertJsonPath('data.weekly.0.weekday',1)->assertJsonPath('data.configured',true);
        $this->putJson('/api/v1/technician/schedule',$payload)->assertConflict();
        $payload['version']=2;
        $payload['weekly'][0]['ends_at']='07:00';
        $this->putJson('/api/v1/technician/schedule',$payload)->assertUnprocessable();
        $customer=User::create(['name'=>'Customer','email'=>'customer@example.test','password'=>'Password12345','role'=>'customer']);
        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/technician/schedule')->assertForbidden();
        $this->putJson('/api/v1/technician/schedule',$payload)->assertForbidden();
    }

    public function test_appointments_reject_overlap_and_travel_buffer_but_allow_adjacent_slots(): void
    {
        $tech=$this->technician();
        $customer=User::create(['name'=>'Customer','email'=>'customer@example.test','password'=>'Password12345','role'=>'customer']);
        $create=fn($ref)=>Booking::create(['reference'=>$ref,'customer_id'=>$customer->id,'technician_id'=>$tech->id,'service_category'=>'Plumbing','description'=>'Inspect and repair the leaking tap','address'=>'Test address','status'=>'requested']);
        $first=$create('FIRST'); $second=$create('SECOND');
        $service=app(TechnicianAvailabilityService::class);
        DB::transaction(function()use($service,$first){$service->book($first,'2030-01-07T09:00:00Z',60,15);$first->save();});
        try {
            DB::transaction(fn()=>$service->book($second,'2030-01-07T10:10:00Z',60,0));
            $this->fail('Travel buffer must reserve capacity.');
        } catch(ValidationException $error) {$this->assertArrayHasKey('scheduled_at',$error->errors());}
        DB::transaction(function()use($service,$second){$service->book($second,'2030-01-07T10:15:00Z',60,0);$second->save();});
        $this->assertNotNull($second->fresh()->scheduled_end_at);
    }

    public function test_timezone_windows_and_blocked_exceptions_are_enforced(): void
    {
        $tech=$this->technician();
        Sanctum::actingAs($tech);
        $service=app(TechnicianAvailabilityService::class);
        $service->save($tech,['version'=>1,'timezone'=>'Africa/Lagos','weekly'=>[['weekday'=>1,'starts_at'=>'09:00','ends_at'=>'17:00']],
            'exceptions'=>[['starts_at'=>'2030-01-07T12:00:00Z','ends_at'=>'2030-01-07T13:00:00Z','available'=>false,'reason'=>'Break']]]);
        DB::transaction(fn()=>$service->assertBookable($tech,\Carbon\CarbonImmutable::parse('2030-01-07T08:00:00Z'),60));
        $this->expectException(ValidationException::class);
        DB::transaction(fn()=>$service->assertBookable($tech,\Carbon\CarbonImmutable::parse('2030-01-07T12:00:00Z'),60));
    }

    public function test_customer_booking_cannot_bypass_a_technician_reservation(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2030-01-01T00:00:00Z'));
        $tech=$this->technician();
        $tech->technicianProfile->update(['service_location'=>'Lagos']);
        $this->seed(\Database\Seeders\AdminMarketplaceSeeder::class);
        $customer=User::create(['name'=>'Customer','email'=>'customer@example.test','password'=>'Password12345','role'=>'customer']);
        Sanctum::actingAs($tech);
        $this->putJson('/api/v1/technician/schedule',['version'=>1,'timezone'=>'Africa/Lagos','weekly'=>[['weekday'=>1,'starts_at'=>'09:00','ends_at'=>'17:00']],'exceptions'=>[]])->assertOk();
        Sanctum::actingAs($customer);
        $payload=['technician_id'=>$tech->id,'service_category'=>'Plumbing','service_area'=>'Lagos','description'=>'Inspect and repair the leaking pipe','address'=>'Test street','scheduled_at'=>'2030-01-07T09:00:00Z'];
        $this->postJson('/api/v1/bookings',$payload)->assertCreated();
        $this->postJson('/api/v1/bookings',$payload)->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->assertDatabaseCount('bookings',1);
        $slots=$this->getJson('/api/v1/technicians/'.$tech->id.'/slots?date=2030-01-07')->assertOk()->json('data.slots');
        $this->assertNotContains('2030-01-07T09:00:00+00:00',array_column($slots,'starts_at'));
        $this->assertNotEmpty($slots);
        $this->travelBack();
    }

    public function test_quotation_duration_cannot_overlap_another_job_and_failure_is_atomic(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2030-01-07T09:15:00Z'));
        $tech = $this->technician();
        $customer = User::create(['name'=>'Customer','email'=>'customer@example.test','password'=>'Password12345','role'=>'customer']);
        $attributes = ['customer_id'=>$customer->id,'technician_id'=>$tech->id,'service_category'=>'Plumbing','description'=>'Repair leaking pipe','address'=>'Test street','status'=>'requested','request_accepted_at'=>now(),'travel_buffer_minutes'=>0];
        $booking = Booking::create($attributes + ['reference'=>'RESIZE','scheduled_at'=>'2030-01-07 09:00:00','scheduled_end_at'=>'2030-01-07 10:00:00']);
        $booking->forceFill(['scheduled_end_at'=>'2030-01-07 10:00:00'])->save();
        Booking::create($attributes + ['reference'=>'NEXT','scheduled_at'=>'2030-01-07 10:30:00','scheduled_end_at'=>'2030-01-07 11:30:00']);
        Sanctum::actingAs($tech);
        $quote = ['currency'=>'NGN','scope'=>'Replace the leaking pipe','diagnosis'=>'Pipe joint is damaged','exclusions'=>'Wall decoration','duration_minutes'=>120,'expires_at'=>'2030-01-08T00:00:00Z','items'=>[['kind'=>'labour','description'=>'Pipe repair','quantity'=>1,'unit_price_minor'=>500000]]];
        $this->postJson('/api/v1/bookings/'.$booking->id.'/quotation', $quote)->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->assertDatabaseCount('quotations', 0);
        $this->assertSame('requested', $booking->fresh()->status->value);
        $this->assertSame('10:00', $booking->fresh()->scheduled_end_at->format('H:i'));
        $quote['duration_minutes'] = 90;
        $this->postJson('/api/v1/bookings/'.$booking->id.'/quotation', $quote)->assertCreated();
        $this->assertSame('10:30', $booking->fresh()->scheduled_end_at->format('H:i'));
        $this->travelBack();
    }
}
