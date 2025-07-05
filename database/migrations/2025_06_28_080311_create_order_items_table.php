<?php

use App\Enums\OrderItemStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_delivery_option_id')->nullable();
            $table->foreign('product_delivery_option_id')->references('id')->on('product_delivery_options')->nullOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();


            $table->string('name');
            $table->string('sku');
            $table->jsonb('product_data_snapshot_json');
            $table->integer('qty_ordered')->default(1);

            $table->unsignedBigInteger('price')->comment('The FULL price per unit.');
            $table->unsignedBigInteger('total')->comment('The FULL line item total (price * qty).');
            $table->string('payment_type');
            $table->unsignedBigInteger('prepayment_amount')->nullable()->comment('Required deposit for a single unit. Snapshot from the product.');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);

            $table->unsignedBigInteger('total_refunded')->default(0);
            $table->integer('qty_refunded')->default(0);

            $table->enum('status', OrderItemStatusEnum::getAllValues())->default(OrderItemStatusEnum::ACTIVE);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
