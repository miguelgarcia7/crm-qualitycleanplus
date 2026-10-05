<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer and worker reviews shown on the marketing home page, managed at
     * /admin/testimonials (marketing-site-audit.md, D2). Seeded once from the
     * legacy Quality Cleaning Plus CMS by `legacy:import-testimonials`.
     */
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('quote');
            $table->string('source', 20); // TestimonialSource: where the review was left
            $table->unsignedTinyInteger('rating');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('photo_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
