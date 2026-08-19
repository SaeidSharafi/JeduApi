<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_promotion_id')->constrained()->cascadeOnDelete();
            // Audit only — coupon deletion must not free a consumed slot.
            $table->foreignId('discount_coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One row per applied Promotion per order.
            $table->unique(['discount_promotion_id', 'order_id'], 'uq_promotion_order_usage');
            // Per-customer limit check reads usage grouped by promotion + customer.
            $table->index(['discount_promotion_id', 'customer_id'], 'idx_promotion_customer_usage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_promotion_usages');
    }
};
