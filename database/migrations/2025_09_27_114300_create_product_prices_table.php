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
        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();

            $table->unsignedInteger('min_price')->index('idx_min_price');
            $table->unsignedInteger('min_original_price');
            $table->unsignedInteger('max_price');
            $table->unsignedInteger('max_original_price');

            $table->boolean('has_discount')->default(false)->index('idx_has_discount');
            $table->boolean('has_featured_price')->default(false)->index('idx_has_featured');
            $table->boolean('has_prepayment')->default(false)->index('idx_has_prepayment');

            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->unsignedInteger('highest_discount_amount')->nullable();

            $table->timestamps();

            // Composite indexes for common sorting patterns
            $table->index(['has_discount', 'min_price'], 'idx_discount_price');
            $table->index(['has_featured_price', 'min_price'], 'idx_featured_price');
            $table->index(['min_price', 'has_discount'], 'idx_price_discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
