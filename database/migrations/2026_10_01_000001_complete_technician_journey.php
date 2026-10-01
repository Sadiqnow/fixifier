<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_profiles', function (Blueprint $t) {
            $t->text('skills')->nullable();
            $t->unsignedBigInteger('indicative_price_minor')->nullable();
            $t->text('availability_notes')->nullable();
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->timestamp('request_accepted_at')->nullable();
            $t->string('visit_status', 24)->nullable();
            $t->unsignedBigInteger('accepted_quotation_id')->nullable();
        });
        Schema::table('quotations', function (Blueprint $t) {
            $t->index('booking_id', 'quotations_booking_history');
            $t->unsignedInteger('version')->default(1);
            $t->text('diagnosis')->nullable();
            $t->text('exclusions')->nullable();
            $t->json('items')->nullable();
            $t->unsignedInteger('duration_minutes')->nullable();
            $t->string('decision', 24)->nullable();
            $t->text('decision_reason')->nullable();
        });
        foreach (Schema::getIndexes('quotations') as $index) {
            if ($index['unique'] && $index['columns'] === ['booking_id']) {
                Schema::table('quotations', fn (Blueprint $t) => $t->dropUnique($index['name']));
            }
        }
        Schema::table('quotations', fn (Blueprint $t) => $t->unique(['booking_id', 'version']));
        Schema::create('work_rounds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->foreignId('technician_id')->nullable()->constrained('users');
            $t->foreignId('dispute_id')->nullable()->constrained();
            $t->text('corrective_work')->nullable();
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->text('completion_notes')->nullable();
            $t->string('review', 24)->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['booking_id', 'number']);
        });
        // Preserve existing history. Missing historical notes/times remain unknown.
        DB::table('bookings')->orderBy('id')->chunkById(100, function ($bookings) {
            foreach ($bookings as $b) {
                for ($n = 1; $n <= $b->current_work_round; $n++) {
                    $d = $n > 1 ? DB::table('disputes')->where('booking_id', $b->id)->where('work_round', $n - 1)->where('decision', 'rework')->first() : null;
                    DB::table('work_rounds')->insert(['booking_id' => $b->id, 'number' => $n, 'technician_id' => $b->technician_id,
                        'dispute_id' => $d?->id, 'corrective_work' => $d?->resolution, 'created_at' => now(), 'updated_at' => now()]);
                }
                $q = DB::table('quotations')->where('booking_id', $b->id)->whereNotNull('accepted_at')->first();
                if ($q) DB::table('bookings')->where('id', $b->id)->update(['accepted_quotation_id' => $q->id]);
            }
        });
        Schema::create('booking_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users');
            $t->unsignedInteger('work_round');
            $t->string('type', 40);
            $t->text('body');
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('booking_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->restrictOnDelete();
            $t->foreignId('uploader_id')->constrained('users');
            $t->foreignId('dispute_id')->nullable()->constrained();
            $t->unsignedInteger('work_round');
            $t->string('type', 24);
            $t->text('storage_path');
            $t->string('mime_type');
            $t->unsignedBigInteger('size_bytes');
            $t->string('sha256', 64);
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('journey_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('booking_id')->nullable()->constrained();
            $t->string('type');
            $t->text('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->foreignId('quotation_id')->nullable()->constrained();
            $t->unsignedBigInteger('platform_fee_minor')->nullable();
            $t->unsignedBigInteger('technician_net_minor')->nullable();
            $t->unsignedBigInteger('refund_minor')->default(0);
            $t->json('fee_snapshot')->nullable();
            $t->string('settlement_reference')->nullable()->unique();
        });
        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained();
            $t->string('provider_event_id')->unique();
            $t->string('type', 24);
            $t->json('payload');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('payment_operations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained();
            $t->string('kind', 24);
            $t->string('reference')->unique();
            $t->string('provider_id')->nullable();
            $t->string('status', 24)->default('pending');
            $t->unsignedBigInteger('amount_minor');
            $t->text('destination')->nullable();
            $t->text('checkout_url')->nullable();
            $t->timestamps();
            $t->unique(['payment_id', 'kind']);
        });
        Schema::create('payout_destinations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained();
            $t->text('recipient_code');
            $t->string('account_name');
            $t->string('last_four', 4);
            $t->timestamp('verified_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Journey history must be retained. Restore a verified backup instead of dropping these records.');
    }
};
