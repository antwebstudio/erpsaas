<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->string('color_secondary_text')->nullable()->after('color_secondary');
            $table->string('color_section_bg_text')->nullable()->after('color_section_bg');
            $table->string('color_group_bg_text')->nullable()->after('color_group_bg');
        });
    }

    public function down(): void
    {
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->dropColumn(['color_secondary_text', 'color_section_bg_text', 'color_group_bg_text']);
        });
    }
};
