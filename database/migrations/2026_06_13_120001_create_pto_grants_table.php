<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log of every PTO accrual movement (ADR-0016): annual refresh, mid-year
     * tier-milestone top-up, or manual HR/admin adjustment. Hours are per-bucket deltas.
     */
    public function up(): void
    {
        Schema::create('pto_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('year_allotment_id')->nullable()->constrained('pto_year_allotments')->nullOnDelete();
            $table->string('grant_type');
            $table->decimal('vacation_hours', 6, 2)->default(0);
            $table->decimal('scheduled_hours', 6, 2)->default(0);
            $table->decimal('unscheduled_hours', 6, 2)->default(0);
            $table->text('reason')->nullable();
            $table->date('effective_date');
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pto_grants');
    }
};
