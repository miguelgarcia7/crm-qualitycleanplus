<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review + promotion trail for applications (Phase 08b-ii): who moved it out
     * of `submitted` and when, the reason when rejected, and who promoted it —
     * the promoter may reverse their own promotion (people-lifecycle.md).
     */
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejected_reason')->nullable();
            $table->foreignId('promoted_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('promoted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('promoted_by');
            $table->dropColumn(['reviewed_at', 'rejected_reason', 'promoted_at']);
        });
    }
};
