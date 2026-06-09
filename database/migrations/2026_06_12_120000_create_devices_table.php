<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A front-desk tablet paired to a property for kiosk clock-in (Phase 07c,
     * ADR-0017). Paired once via `activation_code` → holds a Sanctum token (in
     * personal_access_tokens). The device pins clock events to its property.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->string('name');
            $table->string('activation_code', 12)->unique();
            $table->boolean('is_activated')->default(false);
            $table->string('app_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
