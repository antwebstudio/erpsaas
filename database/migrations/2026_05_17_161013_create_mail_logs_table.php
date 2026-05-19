<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-mail.database.tables.mail_logs', 'mail_logs'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('mailer')->nullable();
            $table->string('subject')->nullable();
            $table->json('from')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->json('reply_to')->nullable();
            $table->longText('html_body')->nullable();
            $table->longText('text_body')->nullable();
            $table->json('headers')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('pending');
            $table->string('provider_message_id')->nullable()->index();
            $table->nullableUuidMorphs('mailable');
            $table->foreignUuid('mail_template_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-mail.database.tables.mail_logs', 'mail_logs'));
    }
};
