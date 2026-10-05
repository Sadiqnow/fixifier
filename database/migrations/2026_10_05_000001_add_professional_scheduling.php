<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_profiles', function (Blueprint $t) {
            $t->string('schedule_timezone', 64)->default('Africa/Lagos');
            $t->boolean('schedule_configured')->default(false);
            $t->unsignedInteger('schedule_version')->default(1);
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->timestamp('scheduled_end_at')->nullable();
            $t->unsignedSmallInteger('travel_buffer_minutes')->default(0);
            $t->index(['technician_id', 'scheduled_at']);
        });
        Schema::create('technician_availability', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $t->unsignedTinyInteger('weekday');
            $t->time('starts_at');
            $t->time('ends_at');
            $t->timestamps();
            $t->index(['technician_id', 'weekday']);
        });
        Schema::create('technician_availability_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->boolean('available')->default(false);
            $t->string('reason', 250);
            $t->timestamps();
            $t->index(['technician_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve scheduling history; use a reviewed restore for rollback.');
    }
};
