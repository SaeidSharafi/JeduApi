<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('vendors');
            $table->unsignedBigInteger('productable_id');
            $table->string('productable_type');
            $table->unsignedBigInteger('term_id');
            $table->foreign('term_id')->references('id')->on('terms');
            $table->string('status')->default(App\Enums\Content\PublicationStatusEnum::DRAFT->value);
            $table->boolean('is_visible')->default(false);
            $table->string('short_description')->nullable();
            $table->string('short_name')->nullable();
            $table->string('name')->nullable();
            // this is just for redundancy and quick access, actual slug is in productable entity
            // so this is not a unique field
            $table->string('slug');
            $table->boolean('is_featured')->default(false);
            $table->jsonb('price_data_cache')->nullable();
            $table->jsonb('details_json');
            $table->timestamps();

            $table->index('status');
            $table->index('is_visible');
            $table->index('is_featured');
            $table->index(['productable_type', 'productable_id']);
            $table->index(['vendor_id', 'term_id']);
            $table->index(['status', 'is_visible']);
            // skip on sqlite
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->fullText(['name', 'short_name', 'short_description', 'slug'], 'products_fulltext_index');
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pgroonga');
            DB::statement('CREATE INDEX products_pgroonga_index ON products USING pgroonga (name, short_name, short_description, slug)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
