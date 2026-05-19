<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-mail.database.tables.mail_templates', 'mail_templates'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('mailable_class')->nullable();
            $table->json('subject');
            $table->json('html_body');
            $table->json('text_body')->nullable();
            $table->json('variables')->nullable();
            $table->string('layout')->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-mail.database.tables.mail_templates', 'mail_templates'));
    }
};
