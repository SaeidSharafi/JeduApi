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
            $table->unsignedBigInteger('product_delivery_option_id');
            $table->foreign('product_delivery_option_id')->references('id')->on('product_delivery_options')->nullOnDelete();
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('vendors');
            $table->integer('quantity')->default(1);
            $table->string('name');
            $table->string('sku');
            $table->jsonb('product_data_snapshot_json');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total');
            $table->enum('status', OrderItemStatusEnum::getAllValues())->default(OrderItemStatusEnum::ACTIVE);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
