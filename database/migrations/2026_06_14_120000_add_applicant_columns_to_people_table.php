<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Durable applicant intake facts (Phase 08b-i). These stay true once the
     * applicant becomes a contractor, so they live on `people` (the identity
     * spine) rather than on the per-application record. The application-event
     * fields + at-the-time legal attestations live on `job_applications`.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->date('dob')->nullable()->after('normalized_phone');

            $table->string('address')->nullable();
            $table->string('apartment_number')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip', 10)->nullable();

            // Work-eligibility declarations (feed I-9 onboarding later).
            $table->boolean('usa_citizen')->nullable();
            $table->boolean('eligible_to_work')->nullable();

            // Emergency contact — durable, useful throughout the contractor lifecycle.
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_address')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'dob',
                'address',
                'apartment_number',
                'city',
                'state',
                'zip',
                'usa_citizen',
                'eligible_to_work',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
                'emergency_contact_address',
            ]);
        });
    }
};
