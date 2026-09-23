<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_profiles', function (Blueprint $table) {
            $table->string('service_location', 100)->nullable();
            $table->unsignedBigInteger('starting_price_minor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('technician_profiles', function (Blueprint $table) {
            $table->dropColumn(['service_location', 'starting_price_minor']);
        });
    }
};
