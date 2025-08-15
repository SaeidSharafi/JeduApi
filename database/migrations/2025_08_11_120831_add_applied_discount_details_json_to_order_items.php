<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Stores a detailed breakdown of all discounts (product-specific and cart-level) that contributed to the final line item price
            $table->json('applied_discount_details_json')->nullable()->after('product_data_snapshot_json');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            //
        });
    }
};
