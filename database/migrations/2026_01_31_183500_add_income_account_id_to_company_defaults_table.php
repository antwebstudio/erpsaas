<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_defaults', function (Blueprint $table) {
            $table->foreignId('income_account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_defaults', function (Blueprint $table) {
            $table->dropForeign(['income_account_id']);
            $table->dropColumn('income_account_id');
        });
    }
};
