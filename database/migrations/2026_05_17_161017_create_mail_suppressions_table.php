<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-mail.database.tables.mail_suppressions', 'mail_suppressions'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->index();
            $table->string('reason');
            $table->string('provider')->nullable();
            $table->foreignUuid('mail_log_id')->nullable()->index();
            $table->timestamp('suppressed_at');
            $table->string('tenant_id')->nullable()->index();

            $table->unique(['email', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-mail.database.tables.mail_suppressions', 'mail_suppressions'));
    }
};
