<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per line of the uploaded Excel file (40-flows/import-hours.md). Carries
     * the raw parsed values, the match/resolution state, and — after commit — a link
     * to the work order + time entry it produced. This is the audit anchor a dispute
     * traces back to: invoice item → time entry → source_metadata → this row → raw_data.
     */
    public function up(): void
    {
        Schema::create('import_batch_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');                           // normalized parsed values for the row
            $table->foreignId('matched_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('existing_wo_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('status')->default('unmatched')->index();
            $table->string('resolution')->nullable();           // e.g. use_file, use_existing, create_contractor, skip
            $table->foreignId('resulting_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('resulting_time_entry_id')->nullable()->constrained('time_entries')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batch_rows');
    }
};
