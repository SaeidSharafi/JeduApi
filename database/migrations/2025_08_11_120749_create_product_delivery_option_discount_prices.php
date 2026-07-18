<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_delivery_option_discount_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('product_delivery_option_id')->primary();
            // use custom fk name sicne mysql has limit of 64 chars
            $table->foreign('product_delivery_option_id', 'pdo_discount_prices_pdo_id_foreign')
                ->references('id')
                ->on('product_delivery_options')
                ->constrained()
                ->onDelete('cascade');
            $table->unsignedBigInteger('discount_promotion_id');
            $table->foreign('discount_promotion_id', 'pdo_discount_prices_discount_promotion_id_foreign')
                ->references('id')
                ->on('discount_promotions')
                ->constrained()
                ->onDelete('cascade');

            // The final calculated price after the 'product_specific' discount has been applied.
            $table->unsignedBigInteger('discounted_price');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->timestamps();

            $table->index(['product_delivery_option_id', 'discount_promotion_id'], 'idx_pdo_promotion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_delivery_option_discount_prices');
    }
};
