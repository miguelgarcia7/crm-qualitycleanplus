<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One `people` table holds every human in the system, with a `status`
     * enum tracking lifecycle position (ADR-0004). Roles (Spatie) are separate.
     *
     * Phase 01 creates the SPINE only — identity, auth, status, lifecycle
     * dates, PII/legal-hold, primary recruiter. The bulk application-data and
     * onboarding-checklist columns arrive with their phases (02/08).
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('normalized_phone', 15)->nullable()->index();

            // Authentication (nullable — applicants/contractors may never log in)
            $table->string('password')->nullable();
            $table->rememberToken();

            // Lifecycle (see 20-domain/people-lifecycle.md)
            $table->enum('status', [
                'applicant',
                'contractor_active',
                'contractor_inactive',
                'pending_termination',
                'terminated',
                'staff_active',
                'staff_inactive',
            ])->default('applicant')->index();

            // Immutable history (set once; enforced in the model)
            $table->date('application_date')->nullable();
            $table->timestamp('converted_to_contractor_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->date('hire_date')->nullable(); // W-2 staff anchor (PTO tiers)

            // Ownership — the recruiter who "owns" this contractor (ADR-0019)
            $table->foreignId('primary_recruiter_id')->nullable()
                ->constrained('people')->nullOnDelete();

            // PII / legal hold (ADR-0010, 30-schema/conventions.md)
            $table->boolean('legal_hold')->default(false);
            $table->text('legal_hold_reason')->nullable();
            $table->timestamp('legal_hold_set_at')->nullable();
            $table->foreignId('legal_hold_set_by')->nullable()
                ->constrained('people')->nullOnDelete();
            $table->boolean('is_anonymized')->default(false);
            $table->timestamp('anonymized_at')->nullable();
            $table->foreignId('anonymized_by')->nullable()
                ->constrained('people')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            // Laravel's database session driver writes `user_id` by name — this
            // is session infrastructure, not a domain FK, so it keeps that name.
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('people');
    }
};
