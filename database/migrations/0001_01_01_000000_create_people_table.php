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
     * Identity, auth, lifecycle, applicant intake facts, the onboarding
     * checklist, and PII/legal-hold all live here. The file-pointer columns
     * (avatar, IDs, I-9, W-9, agreement) are declared UNCONSTRAINED because
     * `files` is created later and itself references people (circular); the
     * real FKs are promoted in the create_files migration.
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('name');
            // Unique across soft-deleted rows too — deliberate: one identity per human
            // (rehire reuses the row; see people-lifecycle.md "Email & soft deletes").
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('normalized_phone', 15)->nullable()->index();

            // Durable applicant intake facts (Phase 08b-i) — stay true once the
            // applicant becomes a contractor, so they live on the identity spine.
            $table->date('dob')->nullable();
            $table->string('address')->nullable();
            $table->string('apartment_number')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip', 10)->nullable();

            // Work-eligibility declarations (feed I-9 onboarding).
            $table->boolean('usa_citizen')->nullable();
            $table->boolean('eligible_to_work')->nullable();

            // Emergency contact — durable, useful throughout the contractor lifecycle.
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergency_contact_address')->nullable();

            // Authentication (nullable — applicants/contractors may never log in)
            $table->string('password')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();

            // Profile photo + notification mutes (Phase 09b/09d).
            $table->foreignId('avatar_file_id')->nullable(); // FK in create_files
            $table->json('muted_notifications')->nullable();

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

            // Onboarding checklist (Phase 08b-ii, people-lifecycle.md): per-item
            // document file + timestamp, HR verification for the I-9, a background
            // check status, and HR-waived item keys. Promotion to contractor is
            // gated on completion. File FKs promoted in create_files.
            $table->foreignId('id_front_file_id')->nullable();
            $table->timestamp('id_front_uploaded_at')->nullable();
            $table->foreignId('id_back_file_id')->nullable();
            $table->timestamp('id_back_uploaded_at')->nullable();
            $table->foreignId('i9_file_id')->nullable();
            $table->timestamp('i9_uploaded_at')->nullable();
            $table->foreignId('i9_verified_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('i9_verified_at')->nullable();
            $table->foreignId('w9_file_id')->nullable();
            $table->timestamp('w9_uploaded_at')->nullable();
            $table->foreignId('contractor_agreement_file_id')->nullable();
            $table->timestamp('contractor_agreement_signed_at')->nullable();
            $table->string('background_check_status')->nullable(); // not_required|pending|passed|failed
            $table->timestamp('background_check_completed_at')->nullable();
            $table->json('onboarding_waived_items')->nullable(); // item keys waived by HR

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
