<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-mail.database.tables.mail_template_versions', 'mail_template_versions'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mail_template_id')->constrained(
                config('laravel-mail.database.tables.mail_templates', 'mail_templates')
            )->cascadeOnDelete();
            $table->json('subject');
            $table->json('html_body');
            $table->json('text_body')->nullable();
            $table->string('change_note')->nullable();
            $table->string('author')->nullable();
            $table->unsignedInteger('version_number');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-mail.database.tables.mail_template_versions', 'mail_template_versions'));
    }
};
