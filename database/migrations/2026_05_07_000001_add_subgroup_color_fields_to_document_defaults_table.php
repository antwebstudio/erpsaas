<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->string('color_subgroup_bg')->nullable()->after('color_group_bg_text');
            $table->string('color_subgroup_text')->nullable()->after('color_subgroup_bg');
        });
    }

    public function down(): void
    {
        Schema::table('document_defaults', function (Blueprint $table) {
            $table->dropColumn(['color_subgroup_bg', 'color_subgroup_text']);
        });
    }
};
