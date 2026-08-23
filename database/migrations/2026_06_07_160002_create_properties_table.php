<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Property Bible Profile spine — one row per property QCP services.
     * Identity, contact, timezone (drives time-tracking math), geo (geofencing
     * in Phase 07), billing cycle + tax, and active/inactive status.
     * See 20-domain/property-bible.md §1.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // Identity + contact
            $table->string('name');
            $table->string('pm_name')->nullable();          // PM display name (may have no login)
            $table->string('pm_phone', 32)->nullable();
            $table->string('main_phone', 32)->nullable();    // front desk / switchboard

            // Address
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 64)->nullable();
            $table->string('zip', 16)->nullable();

            // Time + geo
            $table->string('timezone')->default('America/Phoenix'); // IANA string
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius_meters')->default(300);

            // QR clock-in: the public URL carries an unguessable token, minted
            // server-side the first time QR is enabled (never fillable).
            $table->string('qr_token', 32)->nullable()->unique();
            $table->boolean('qr_clock_enabled')->default(false);

            // Billing
            $table->unsignedTinyInteger('closing_day')->nullable(); // day-of-month cycle ends
            $table->decimal('tax_rate', 5, 4)->default(0);          // e.g. 0.0875

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active')->index();

            // How billable hours arrive (ADR-0007): clock_in (device/QR events) or
            // import (weekly Excel through the import wizard, no clock-in).
            $table->enum('time_source', ['clock_in', 'import'])->default('clock_in')->index();

            $table->foreignId('created_by')->nullable()
                ->constrained('people')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
