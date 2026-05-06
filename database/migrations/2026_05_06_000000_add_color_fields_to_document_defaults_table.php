<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->string('color_secondary')->nullable()->after('accent_color');
            $table->string('color_section_bg')->nullable()->after('color_secondary');
            $table->string('color_group_bg')->nullable()->after('color_section_bg');
        });
    }

    public function down(): void
    {
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->dropColumn(['color_secondary', 'color_section_bg', 'color_group_bg']);
        });
    }
};
