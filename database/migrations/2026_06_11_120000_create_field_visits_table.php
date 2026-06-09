<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recruiter field visits (Phase 07b, ADR-0017): operational check-in/out logging,
     * separate from billable time_entries. GPS is informational (was_inside_geofence
     * flag); a selfie is captured on check-in only. `was_late_close` marks a visit
     * closed via the "forgot to check out" path.
     */
    public function up(): void
    {
        Schema::create('field_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->string('status')->default('open')->index();

            $table->timestamp('check_in_at');
            $table->decimal('check_in_gps_lat', 10, 7)->nullable();
            $table->decimal('check_in_gps_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_in_gps_accuracy_meters')->nullable();
            $table->string('check_in_gps_status')->default('ok');
            $table->foreignId('check_in_selfie_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->boolean('was_inside_geofence')->default(false);

            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_out_gps_lat', 10, 7)->nullable();
            $table->decimal('check_out_gps_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_out_gps_accuracy_meters')->nullable();
            $table->string('check_out_gps_status')->nullable();
            $table->boolean('was_late_close')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_visits');
    }
};
