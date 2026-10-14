<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who closed a visit for the recruiter, when the office closes one they
     * forgot (field_visits.close_any). Null when the recruiter checked out.
     */
    public function up(): void
    {
        Schema::table('field_visits', function (Blueprint $table) {
            $table->foreignId('closed_by_person_id')->nullable()->after('was_late_close')->constrained('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('field_visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by_person_id');
        });
    }
};
