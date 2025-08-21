<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_delivery_option_discount_prices', function (Blueprint $table) {
            $table->primary('product_delivery_option_id');
            //use custom fk name sicne mysql has limit of 64 chars
            $table->foreignId('product_delivery_option_id')
                ->index('pdo_discount_prices_pdo_id_foreign')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('discount_promotion_id')
                ->index('pdo_discount_prices_discount_promotion_id_foreign')
                ->constrained()
                ->onDelete('cascade');

            // The final calculated price after the 'product_specific' discount has been applied.
            $table->unsignedBigInteger('discounted_price');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_delivery_option_discount_prices');
    }
};
