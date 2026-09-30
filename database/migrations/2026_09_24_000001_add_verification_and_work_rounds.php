<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_profiles', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('current_work_round')->default(1);
        });
        Schema::table('job_evidence', function (Blueprint $table) {
            $table->unsignedInteger('work_round')->default(1);
            $table->index(['booking_id', 'work_round', 'type']);
        });
        Schema::table('disputes', function (Blueprint $table) {
            $table->unsignedInteger('work_round')->default(1);
            $table->string('decision', 24)->nullable();
            // Install a replacement FK-supporting index before removing the old unique key.
            $table->unique(['booking_id', 'work_round']);
        });
        // Imported SQL and Laravel installations use different names for this key.
        foreach (Schema::getIndexes('disputes') as $index) {
            if ($index['unique'] && $index['columns'] === ['booking_id']) {
                Schema::table('disputes', fn (Blueprint $table) => $table->dropUnique($index['name']));
            }
        }
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 50);
            $table->text('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('status', 24)->default('pending')->index();
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });
        Schema::create('kyc_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_document_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24);
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Refuse a destructive rollback after rounds or compliance records are in use.
        if (DB::table('bookings')->where('current_work_round', '>', 1)->exists()
            || DB::table('job_evidence')->where('work_round', '>', 1)->exists()
            || DB::table('disputes')->where('work_round', '>', 1)->exists()
            || DB::table('kyc_documents')->exists()) {
            throw new RuntimeException('Archive and restore verification history explicitly; automatic rollback would lose records.');
        }
        Schema::dropIfExists('kyc_decisions');
        Schema::dropIfExists('kyc_documents');
        Schema::table('disputes', fn (Blueprint $table) => $table->unique('booking_id'));
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropUnique(['booking_id', 'work_round']);
            $table->dropColumn(['work_round', 'decision']);
        });
        Schema::table('job_evidence', function (Blueprint $table) {
            $table->dropIndex(['booking_id', 'work_round', 'type']);
            $table->dropColumn('work_round');
        });
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('current_work_round'));
        Schema::table('technician_profiles', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
