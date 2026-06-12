<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-person notification mutes: a JSON list of NotificationCategory values
     * the person opted out of. Null/empty = receive everything (the default).
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->json('muted_notifications')->nullable()->after('avatar_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('muted_notifications');
        });
    }
};
