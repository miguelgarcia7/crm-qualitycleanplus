<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A workflow instance — one per request/process (termination, supply request,
     * …). Definitions live in code (ADR-0026); this table + workflow_steps are
     * the generic persistence + audit substrate. `subject` is a polymorphic link
     * to the entity the workflow acts on (e.g. a SupplyRequest or a Person).
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // WorkflowType value
            $table->nullableMorphs('subject'); // subject_type + subject_id
            $table->foreignId('initiator_id')->nullable()->constrained('people')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled', 'rejected'])
                ->default('pending')->index();
            $table->unsignedInteger('current_step_index')->default(0);
            $table->json('data')->nullable(); // workflow-specific payload
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
