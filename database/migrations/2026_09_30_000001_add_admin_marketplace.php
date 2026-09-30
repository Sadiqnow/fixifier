<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_active')->default(true));
        Schema::table('technician_profiles', function (Blueprint $t) {
            $t->boolean('is_available')->default(true);
            $t->unsignedInteger('verification_version')->default(1);
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->string('service_area', 100)->nullable();
            $t->unsignedInteger('lock_version')->default(1);
            $t->string('settlement_status', 24)->nullable()->index();
            $t->timestamp('release_approved_at')->nullable();
        });
        foreach (['categories', 'service_areas'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('name', 255);
                $t->string('normalized_name', 255)->unique();
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
        Schema::create('marketplace_settings', function (Blueprint $t) {
            $t->id();
            $t->json('values');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('technician_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technician_profile_id')->constrained()->restrictOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $t->string('from_status', 24);
            $t->string('to_status', 24);
            $t->text('reason');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('evidence_flags', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('work_round');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['booking_id', 'work_round']);
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $t->unsignedTinyInteger('stars');
            $t->text('comment');
            $t->string('status', 24)->default('published');
            $t->text('moderation_reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('technician_decisions')->exists() || DB::table('evidence_flags')->exists()
            || DB::table('reviews')->exists() || DB::table('marketplace_settings')->exists()
            || DB::table('categories')->exists() || DB::table('service_areas')->exists()
            || DB::table('bookings')->whereNotNull('settlement_status')->exists()
            || DB::table('bookings')->whereNotNull('service_area')->exists()) {
            throw new RuntimeException('Admin records are in use. Restore a reviewed backup instead of dropping history.');
        }
        foreach (['reviews', 'evidence_flags', 'technician_decisions', 'marketplace_settings', 'service_areas', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex(['settlement_status']);
            $t->dropColumn(['service_area', 'lock_version', 'settlement_status', 'release_approved_at']);
        });
        Schema::table('technician_profiles', fn (Blueprint $t) => $t->dropColumn(['is_available', 'verification_version']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_active'));
    }
};
