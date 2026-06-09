<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding checklist (Phase 08b-ii, people-lifecycle.md): per-item document
     * file + timestamp, HR verification for the I-9, a background-check status,
     * and HR-waived item keys. Promotion to contractor is gated on completion.
     * Uniform issuance is tracked through inventory, not here.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('id_front_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamp('id_front_uploaded_at')->nullable();
            $table->foreignId('id_back_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamp('id_back_uploaded_at')->nullable();

            $table->foreignId('i9_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamp('i9_uploaded_at')->nullable();
            $table->foreignId('i9_verified_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('i9_verified_at')->nullable();

            $table->foreignId('w9_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamp('w9_uploaded_at')->nullable();

            $table->foreignId('contractor_agreement_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamp('contractor_agreement_signed_at')->nullable();

            $table->string('background_check_status')->nullable(); // not_required|pending|passed|failed
            $table->timestamp('background_check_completed_at')->nullable();

            $table->json('onboarding_waived_items')->nullable(); // item keys waived by HR
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            foreach (['id_front_file_id', 'id_back_file_id', 'i9_file_id', 'i9_verified_by', 'w9_file_id', 'contractor_agreement_file_id'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn([
                'id_front_uploaded_at',
                'id_back_uploaded_at',
                'i9_uploaded_at',
                'i9_verified_at',
                'w9_uploaded_at',
                'contractor_agreement_signed_at',
                'background_check_status',
                'background_check_completed_at',
                'onboarding_waived_items',
            ]);
        });
    }
};
