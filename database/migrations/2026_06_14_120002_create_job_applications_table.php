<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job applications (Phase 08b-i) — one immutable row per public submission.
     * Records the application *event*: which posting, the desired terms, and the
     * legal attestations as declared at submission time. Durable identity/contact
     * facts live on `people`; this row links to the created/matched person.
     */
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('job_postings')->nullOnDelete();

            // Name as submitted (legal record of what they typed).
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('second_last_name')->nullable();

            // Application-event terms.
            $table->string('desired_position')->nullable();
            $table->string('desired_salary')->nullable();
            $table->date('desired_start_date')->nullable();

            // At-the-time declarations.
            $table->boolean('transportation')->nullable();
            $table->boolean('work_at_qcp')->nullable();
            $table->string('work_at_qcp_explain')->nullable();
            $table->boolean('another_staff_agency')->nullable();
            $table->string('non_complete')->nullable();
            $table->boolean('convicted_felon')->nullable();
            $table->string('felony_conviction')->nullable();

            $table->boolean('acknowledgement')->default(false);

            $table->string('status')->default('submitted')->index();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
