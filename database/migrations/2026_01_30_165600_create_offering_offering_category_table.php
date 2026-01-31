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
        Schema::create('offering_offering_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offering_category_id')->constrained('offering_categories')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offering_offering_category');
    }
};
