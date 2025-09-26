<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_delivery_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('fulfillment_type');
            $table->string('delivery_method');
            $table->unsignedBigInteger('price');
            $table->integer('capacity')->nullable();
            $table->boolean('allow_multiple_quantity')->default(false);
            $table->string('status')->index()->default(\App\Enums\Content\PublicationStatusEnum::DRAFT->value);
            $table->boolean('is_prepayment_available')->default(false);
            $table->unsignedBigInteger('prepayment_amount')->nullable();
            $table->jsonb('details_json');
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('featured_price')->nullable();
            $table->dateTime('featured_price_start_date')->nullable();
            $table->dateTime('featured_price_end_date')->nullable();
            $table->date('registration_start_date')->nullable();
            $table->date('registration_end_date')->nullable();
            $table->date('available_from')->nullable();
            $table->date('available_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_delivery_options');
    }
};
