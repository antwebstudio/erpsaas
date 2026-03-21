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
        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('template_company_id')->nullable()->constrained('companies')->nullOnDelete();
        });

        Schema::table('variation_orders', function (Blueprint $table) {
            $table->foreignId('template_company_id')->nullable()->constrained('companies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variation_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_company_id');
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_company_id');
        });
    }
};
