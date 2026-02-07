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
        Schema::table('document_line_item_groups', function (Blueprint $table) {
            $table->foreignId('offering_category_id')->after('company_id')->nullable()->constrained('offering_categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_line_item_groups', function (Blueprint $table) {
            $table->dropForeign(['offering_category_id']);
            $table->dropColumn('offering_category_id');
        });
    }
};
