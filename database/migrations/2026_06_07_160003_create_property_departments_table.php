<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-(property, department) operational unit with its manager contact.
     * Managers are usually free text — they rarely have logins.
     * See 20-domain/property-bible.md §2.
     */
    public function up(): void
    {
        Schema::create('property_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('manager_name')->nullable();
            $table->string('manager_phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['property_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_departments');
    }
};
