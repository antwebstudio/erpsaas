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
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->text('materials_guide')->nullable()->after('terms');
            $table->text('terms_and_conditions')->nullable()->after('materials_guide');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->dropColumn(['materials_guide', 'terms_and_conditions']);
        });
    }
};
