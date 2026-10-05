<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A public application whose email matches someone other than a live
     * applicant (staff, contractor, archived) is no longer attached to that
     * record automatically: anyone can type anyone's email. It lands on a new
     * applicant with a placeholder email, keeps what was typed here, and
     * points at the match until a recruiter links or dismisses it.
     */
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('submitted_email')->nullable()->after('second_last_name');
            $table->foreignId('matched_person_id')->nullable()->after('person_id')
                ->constrained('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_person_id');
            $table->dropColumn('submitted_email');
        });
    }
};
