<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Back-office inbox for website leads (/admin/inquiries): which site the lead
     * came from, the reCAPTCHA verdict the lead email showed, and who followed it up.
     */
    public function up(): void
    {
        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->string('locale', 2)->default('en')->after('message');
            $table->string('spam_check')->nullable()->after('locale');
            $table->timestamp('handled_at')->nullable()->index()->after('spam_check');
            $table->foreignId('handled_by_id')->nullable()->after('handled_at')
                ->constrained('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_by_id');
            $table->dropColumn(['locale', 'spam_check', 'handled_at']);
        });
    }
};
