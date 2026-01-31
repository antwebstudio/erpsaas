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
        Schema::table('offerings', function (Blueprint $table) {
            $table->string('unit')->nullable()->after('description');
        });

        Schema::table('document_line_items', function (Blueprint $table) {
            $table->string('unit')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offerings', function (Blueprint $table) {
            $table->dropColumn('unit');
        });

        Schema::table('document_line_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
