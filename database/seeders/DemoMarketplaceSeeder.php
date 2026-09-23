<?php

namespace Database\Seeders;

use App\Models\{AuditEvent, Booking, Dispute, JobEvidence, Payment, Quotation, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB, Storage};

class DemoMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data is only available in local and testing environments.');
        }

        DB::transaction(function () {
            $account = fn ($email, $name, $role) => User::firstOrCreate(
                ['email' => $email.'@demo.fixifier.test'],
                ['name' => $name.' (Demo)', 'role' => $role, 'password' => 'DemoFixifier2026!', 'email_verified_at' => now()]
            );
            $admin = $account('admin', 'Demo Administrator', 'admin');
            $customers = collect(['Amina Yusuf', 'Emeka Obi', 'Hauwa Sani'])->map(fn ($name, $i) => $account('customer'.($i + 1), $name, 'customer'));
            $trades = ['Electrical repairs', 'AC servicing', 'Plumbing', 'Generator maintenance'];
            $technicians = collect(['Musa Ibrahim', 'Amina Bello', 'Chinedu Okafor', 'Zainab Ali'])->map(function ($name, $i) use ($account, $trades) {
                $user = $account('technician'.($i + 1), $name, 'technician');
                DB::table('technician_profiles')->insertOrIgnore([
                    'user_id' => $user->id, 'trade' => $trades[$i], 'bio' => 'Fictional technician profile for local demonstration.',
                    'years_experience' => 3 + $i, 'kyc_status' => $i < 2 ? 'verified' : 'pending',
                    'verified_at' => $i < 2 ? now()->subDays(10) : null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('technician_profiles')->where('user_id', $user->id)->whereNull('service_location')->update(['service_location' => ['Abuja', 'Lagos', 'Katsina', 'Abuja'][$i]]);
                DB::table('technician_profiles')->where('user_id', $user->id)->whereNull('starting_price_minor')->update(['starting_price_minor' => (8000 + $i * 2000) * 100]);
                return $user;
                
            });
            $states = ['requested', 'quoted', 'confirmed', 'in_progress', 'evidence_submitted', 'completed', 'disputed', 'cancelled', 'requested', 'quoted', 'evidence_submitted', 'completed'];
            // Append every workflow stage for each technician without changing existing references.
            foreach (['requested', 'quoted', 'confirmed', 'in_progress', 'evidence_submitted', 'completed', 'disputed', 'cancelled'] as $stage) {
                foreach ($technicians as $technician) $states[] = $stage;
            }
            foreach ($states as $i => $status) {
                $reference = 'DEMO-FX-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
                if (Booking::where('reference', $reference)->exists()) continue;
                $customer = $customers[$i % 3];
                $technician = $technicians[$i % 4];
                $amount = (15000 + $i * 5000) * 100;
                $booking = Booking::create([
                    'reference' => $reference, 'customer_id' => $customer->id, 'technician_id' => $technician->id,
                    'service_category' => $trades[$i % 4], 'description' => 'SIMULATED REQUEST: Inspect and repair the reported fault, test operation and document the result.',
                    'address' => 'Demo property '.($i + 1).', '.['Abuja', 'Lagos', 'Katsina'][$i % 3],
                    'scheduled_at' => in_array($status, ['requested', 'quoted', 'confirmed']) ? now()->addDays(2) : now()->subDays(2), 'status' => $status,
                ]);
                if ($status !== 'requested') {
                    Quotation::create(['booking_id' => $booking->id, 'amount_minor' => $amount, 'currency' => 'NGN',
                        'scope' => 'Demo quotation: diagnosis, repair parts, labour and operational checks.',
                        'expires_at' => now()->addDays(7), 'accepted_at' => in_array($status, ['quoted', 'cancelled']) ? null : now()->subDays(3)]);
                }
                if (in_array($status, ['confirmed', 'in_progress', 'evidence_submitted', 'completed', 'disputed'])) {
                    Payment::create(['booking_id' => $booking->id, 'provider' => 'demo', 'provider_reference' => $reference.'-PAY',
                        'amount_minor' => $amount, 'currency' => 'NGN', 'status' => $status === 'completed' ? 'released' : 'authorized',
                        'authorized_at' => now()->subDays(3), 'released_at' => $status === 'completed' ? now()->subDay() : null]);
                }
                $types = $status === 'in_progress' ? ['before'] : (in_array($status, ['evidence_submitted', 'completed', 'disputed']) ? ['before', 'after'] : []);
                foreach ($types as $type) {
                    $path = "demo/evidence/{$reference}-{$type}.svg";
                    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360"><rect width="640" height="360" fill="#eaf1ff"/><text x="320" y="150" text-anchor="middle" font-family="sans-serif" font-size="26" fill="#175cd3">SIMULATED '.strtoupper($type).' EVIDENCE</text><text x="320" y="205" text-anchor="middle" font-family="sans-serif" font-size="18">'.$reference.' — not a real job photo</text></svg>';
                    Storage::disk('private')->put($path, $svg);
                    JobEvidence::create(['booking_id' => $booking->id, 'technician_id' => $technician->id, 'type' => $type,
                        'storage_path' => $path, 'mime_type' => 'image/svg+xml', 'size_bytes' => strlen($svg), 'sha256' => hash('sha256', $svg),
                        'note' => 'Simulated '.$type.' evidence for testing only.', 'captured_at' => now()->subDays($type === 'before' ? 2 : 1)]);
                }
                if ($status === 'disputed') {
                    Dispute::create(['booking_id' => $booking->id, 'opened_by' => $customer->id, 'reason' => 'Demo: fault persists',
                        'details' => 'Simulated customer reports the original fault returned after the repair. Review evidence and arrange rework.', 'status' => 'open']);
                }
                AuditEvent::create(['actor_id' => $admin->id, 'action' => 'demo.booking.seeded', 'subject_type' => Booking::class,
                    'subject_id' => $booking->id, 'metadata' => ['simulated' => true, 'status' => $status], 'created_at' => now()]);
            }
        });
        $this->command?->info('Demo marketplace ready. Password for newly created demo accounts: DemoFixifier2026!');
    }
}
