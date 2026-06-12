<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polymorphic uploaded-file store. Introduced for contract documents; reused
     * later by KB attachments, invoices, etc. Local disk for now; S3 in prod.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->morphs('fileable'); // fileable_type + fileable_id (+ index)
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->foreignId('uploaded_by')->nullable()
                ->constrained('people')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // people ↔ files is circular (files.uploaded_by → people). The file-pointer
        // columns on people are declared unconstrained in create_people; promote
        // them to real FKs now that files exists.
        Schema::table('people', function (Blueprint $table) {
            foreach (self::PEOPLE_FILE_POINTERS as $column) {
                $table->foreign($column)->references('id')->on('files')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            foreach (self::PEOPLE_FILE_POINTERS as $column) {
                $table->dropForeign([$column]);
            }
        });

        Schema::dropIfExists('files');
    }

    private const PEOPLE_FILE_POINTERS = [
        'avatar_file_id',
        'id_front_file_id',
        'id_back_file_id',
        'i9_file_id',
        'w9_file_id',
        'contractor_agreement_file_id',
    ];
};
