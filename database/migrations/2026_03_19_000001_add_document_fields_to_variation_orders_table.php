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
        Schema::table('variation_orders', function (Blueprint $table) {
            $table->string('logo')->after('client_id')->nullable();
            $table->string('header')->after('logo')->nullable();
            $table->string('subheader')->after('header')->nullable();
            $table->string('footer')->after('terms')->nullable();
            $table->string('discount_method')->after('currency_code')->default('per_line_item');
            $table->string('discount_computation')->after('discount_method')->default('percentage');
            $table->unsignedBigInteger('discount_rate')->after('discount_computation')->default(0);
            $table->timestamp('approved_at')->after('expiry_date')->nullable();
            $table->timestamp('accepted_at')->after('approved_at')->nullable();
            $table->timestamp('converted_at')->after('accepted_at')->nullable();
            $table->timestamp('declined_at')->after('converted_at')->nullable();
            $table->timestamp('last_sent_at')->after('declined_at')->nullable();
            $table->timestamp('last_viewed_at')->after('last_sent_at')->nullable();
            $table->boolean('is_template')->after('footer')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('variation_orders', function (Blueprint $table) {
            $table->dropColumn([
                'logo',
                'header',
                'subheader',
                'footer',
                'discount_method',
                'discount_computation',
                'discount_rate',
            ]);
        });
    }
};
