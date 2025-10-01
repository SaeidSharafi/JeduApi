<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('related_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('related_product_id');
            $table->string('relation_type')->default(App\Enums\Product\RelationTypeEnum::RELATED->value);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            $table->foreign('related_product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');

            // Indexes for better query performance
            $table->index(['product_id', 'relation_type']);
            $table->index('related_product_id');

            // Ensure a product cannot be related to itself
            // and prevent duplicate relations of the same type
            $table->unique(['product_id', 'related_product_id', 'relation_type'], 'unique_product_relation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('related_products');
    }
};
