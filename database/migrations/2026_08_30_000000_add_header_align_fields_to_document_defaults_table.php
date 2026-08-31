<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->string('section_header_align')->nullable()->after('color_section_bg_text');
            $table->string('group_header_align')->nullable()->after('color_group_bg_text');
            $table->string('subgroup_header_align')->nullable()->after('color_subgroup_text');
        });
    }

    public function down(): void
    {
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->dropColumn(['section_header_align', 'group_header_align', 'subgroup_header_align']);
        });
    }
};
