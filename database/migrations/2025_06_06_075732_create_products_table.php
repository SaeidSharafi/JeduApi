<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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
            $table->enum('status', \App\Enums\PublicationStatusEnum::getAllValues());
            $table->boolean('is_visible')->default(false);
            $table->string('short_description');
            $table->string('short_name');
            $table->string('name');
            $table->boolean('is_featured')->default(false);
            $table->jsonb('details_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
