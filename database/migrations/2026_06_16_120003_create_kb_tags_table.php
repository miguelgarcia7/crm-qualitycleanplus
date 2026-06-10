<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KB tags (Phase 08c) — flat labels, auto-created when authors type new
     * ones. `slug` is the public route key.
     */
    public function up(): void
    {
        Schema::create('kb_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_tags');
    }
};
