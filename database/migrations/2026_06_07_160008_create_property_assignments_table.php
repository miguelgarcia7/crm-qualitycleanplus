<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which recruiters / property managers are assigned to which properties.
     * Drives the "(own)" scoping in policies — a recruiter sees only the
     * properties they're assigned to. See 10-architecture/permissions-matrix.md.
     */
    public function up(): void
    {
        Schema::create('property_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->enum('role', ['recruiter', 'property_manager']);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['property_id', 'person_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_assignments');
    }
};
