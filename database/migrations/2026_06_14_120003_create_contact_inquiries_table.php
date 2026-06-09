<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketing-site contact leads (Phase 08b-i) — both the "Job Seekers" and
     * "Business" inquiry forms post here, discriminated by `type`. Stored rather
     * than emailed so they're auditable; a back-office review surface can come later.
     */
    public function up(): void
    {
        Schema::create('contact_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // business | job_seeker
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('company')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('inquiry_type')->nullable();   // business: type of inquiry
            $table->string('call_back_time')->nullable(); // job seeker: best time to call
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');
    }
};
