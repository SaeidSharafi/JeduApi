<?php

declare(strict_types=1);

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
        Schema::create('bundle_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_delivery_option_id')->constrained()->restrictOnDelete();
            $table->string('bundle_name');
            $table->string('product_name');
            $table->string('name');
            $table->string('sku');
            $table->unsignedBigInteger('base_value');
            $table->unsignedBigInteger('selling_price');
            $table->unsignedInteger('composition_version');
            $table->string('checkout_status');
            $table->jsonb('product_data_snapshot_json');
            $table->timestamps();
            $table->unique(['order_id', 'product_delivery_option_id']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('bundle_purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['bundle_purchase_id', 'product_delivery_option_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique(['bundle_purchase_id', 'product_delivery_option_id']);
            $table->dropConstrainedForeignId('bundle_purchase_id');
        });
        Schema::dropIfExists('bundle_purchases');
    }
};
