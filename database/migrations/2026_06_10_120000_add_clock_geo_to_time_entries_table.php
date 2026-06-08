<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GPS coordinates + selfie file references captured on QR clock in/out
     * (Phase 07a, ADR-0017). All nullable — only the QR (`clock_method = qr`)
     * path populates them; tablet/manual/imported entries leave them null.
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->decimal('clock_in_gps_lat', 10, 7)->nullable()->after('timezone');
            $table->decimal('clock_in_gps_lng', 10, 7)->nullable()->after('clock_in_gps_lat');
            $table->unsignedInteger('clock_in_gps_accuracy_meters')->nullable()->after('clock_in_gps_lng');
            $table->foreignId('clock_in_selfie_file_id')->nullable()->after('clock_in_gps_accuracy_meters')
                ->constrained('files')->nullOnDelete();

            $table->decimal('clock_out_gps_lat', 10, 7)->nullable()->after('clock_in_selfie_file_id');
            $table->decimal('clock_out_gps_lng', 10, 7)->nullable()->after('clock_out_gps_lat');
            $table->unsignedInteger('clock_out_gps_accuracy_meters')->nullable()->after('clock_out_gps_lng');
            $table->foreignId('clock_out_selfie_file_id')->nullable()->after('clock_out_gps_accuracy_meters')
                ->constrained('files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clock_in_selfie_file_id');
            $table->dropConstrainedForeignId('clock_out_selfie_file_id');
            $table->dropColumn([
                'clock_in_gps_lat', 'clock_in_gps_lng', 'clock_in_gps_accuracy_meters',
                'clock_out_gps_lat', 'clock_out_gps_lng', 'clock_out_gps_accuracy_meters',
            ]);
        });
    }
};
