<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-level settings that belong to the business, not the deployment.
 *
 * QCP's own invoicing identity used to live in `config/qcp.php` (env-driven),
 * which meant a phone-number change needed a developer and a redeploy, and a
 * blank field was invisible until it appeared on an invoice a client already
 * had. Invoices freeze the invoicer block at generation (ADR-0006), so a wrong
 * value there is permanent — that is the one setting that most needed a UI.
 *
 * Deliberately a flat key/value store: there is one group of settings today and
 * no reason to model a schema for the second one before it exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
