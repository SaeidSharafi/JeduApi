<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_promotions', function (Blueprint $table) {
            $table->index(['is_active', 'starts_at', 'ends_at'], 'idx_active_dates');
            $table->index(['type', 'priority'], 'idx_type_priority');
        });
        Schema::table('discount_coupons', function (Blueprint $table) {
            $table->index(['is_active', 'code'], 'idx_active_code');
        });
        Schema::table('product_delivery_option_discount_prices', function (Blueprint $table) {
            $table->index(['product_delivery_option_id', 'discount_promotion_id'], 'idx_pdo_promotion');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'idx_customer_created');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_status_created');
        });
    }

    public function down(): void
    {
        Schema::table('', function (Blueprint $table) {
            //
        });
    }
};
