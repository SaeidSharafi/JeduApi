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
            $table->dateTime('event_start_at')->nullable();
            $table->dateTime('event_ended_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('is_visible');
            $table->index('is_featured');
            $table->boolean('has_published_delivery_option')->default(false)->index();
            $table->string('productable_status')->default('draft')->index();
            $table->boolean('is_term_active')->default(true)->index();
            $table->date('earliest_registration_start')->nullable();
            $table->date('latest_registration_end')->nullable();
            $table->date('earliest_availability_start')->nullable();
            $table->date('latest_availability_end')->nullable();
            $table->boolean('near_capacity')->default(false)->index();
            $table->decimal('max_capacity_utilization', 5, 2)->default(0);
            $table->index(['productable_type', 'productable_id']);
            $table->index(['vendor_id', 'term_id']);
            $table->index(['status', 'is_visible']);
            $table->index('event_start_at');
            $table->index('event_ended_at');
            // skip on sqlite
            if (DB::connection()->getDriverName() === 'mysql') {
                $table->fullText(['name', 'short_name', 'short_description', 'slug'], 'products_fulltext_index');
            }
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('CREATE INDEX products_pgroonga_index ON products USING pgroonga (name, short_name, short_description, slug) WHERE use_pgroonga();');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
