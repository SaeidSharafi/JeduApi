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
        Schema::table('product_delivery_options', function (Blueprint $table): void {
            $table->timestamp('bundle_review_required_at')->nullable()->index();
            $table->json('bundle_review_reasons')->nullable();
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->unsignedInteger('composition_version')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropColumn('composition_version');
        });

        Schema::table('product_delivery_options', function (Blueprint $table): void {
            $table->dropIndex(['bundle_review_required_at']);
            $table->dropColumn(['bundle_review_required_at', 'bundle_review_reasons']);
        });
    }
};
