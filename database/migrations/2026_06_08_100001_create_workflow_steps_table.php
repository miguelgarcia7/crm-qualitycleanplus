<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A materialized step of a workflow. Steps are created up-front from the
     * definition's blueprint when the workflow starts. `system` steps run
     * automatically; `human` steps wait in the assignee's My Tasks inbox.
     * Assignment is by specific person (`assigned_to`) or by role
     * (`assigned_role`); `required_permission` further gates who may act.
     */
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedInteger('step_index');
            $table->string('step_key')->nullable(); // stable id for hooks (e.g. 'fulfill')
            $table->string('name');
            $table->enum('step_type', ['approval', 'action', 'notification']);
            $table->enum('actor', ['system', 'human'])->default('human');
            $table->foreignId('assigned_to')->nullable()->constrained('people')->nullOnDelete();
            $table->string('assigned_role')->nullable();
            $table->string('required_permission')->nullable();
            $table->enum('status', ['pending', 'done', 'skipped', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->index(['workflow_id', 'step_index']);
            $table->index(['status', 'assigned_to']);
            $table->index(['status', 'assigned_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
