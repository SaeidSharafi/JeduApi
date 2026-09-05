<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('full_name');
            $table->string('short_name');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->json('properties')->nullable();
            $table->json('additional_info')->nullable();
            $table->json('faq')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('product_delivery_options', function (Blueprint $table): void {
            $table->unsignedInteger('composition_version')->default(1);
            $table->index(['delivery_method', 'fulfillment_type']);
        });
        Schema::create('bundle_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bundle_product_delivery_option_id')->constrained('product_delivery_options')->cascadeOnDelete();
            $table->foreignId('component_product_delivery_option_id')->constrained('product_delivery_options')->restrictOnDelete();
            $table->unsignedBigInteger('allocation');
            $table->timestamps();
            $table->unique(['bundle_product_delivery_option_id', 'component_product_delivery_option_id']);
            $table->index('component_product_delivery_option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_components');
        Schema::table('product_delivery_options', function (Blueprint $table): void {
            $table->dropIndex(['delivery_method', 'fulfillment_type']);
            $table->dropColumn('composition_version');
        });
        Schema::dropIfExists('bundles');
    }
};
