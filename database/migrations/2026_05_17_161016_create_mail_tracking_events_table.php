<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-mail.database.tables.mail_tracking_events', 'mail_tracking_events'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mail_log_id')->constrained(
                config('laravel-mail.database.tables.mail_logs', 'mail_logs')
            )->cascadeOnDelete();
            $table->string('type');
            $table->string('provider');
            $table->string('provider_event_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('recipient')->nullable();
            $table->string('url')->nullable();
            $table->string('bounce_type')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['mail_log_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-mail.database.tables.mail_tracking_events', 'mail_tracking_events'));
    }
};
