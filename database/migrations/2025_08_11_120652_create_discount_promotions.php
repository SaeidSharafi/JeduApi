<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_promotions', function (Blueprint $table) {
            $table->id();
            // Core Details
            $table->string('name'); // Admin-facing name, e.g., "End of Year Course Sale"
            $table->text('description')->nullable(); // Internal notes for admins

            // Behavior & Type
            $table->string('type');
            $table->boolean('is_active')->default(false)->index(); // Master switch for the promotion

            // Scheduling
            $table->timestamp('starts_at')->nullable(); // When the promotion becomes valid
            $table->timestamp('ends_at')->nullable(); // When the promotion expires

            // Rule Logic & Conflict Resolution
            $table->unsignedInteger('priority')->default(0)->index(); // Higher number runs first to resolve conflicts
            $table->boolean('stop_processing_subsequent_rules')->default(false); // If true, prevents discount stacking

            // Usage Limits & Tracking
            $table->unsignedInteger('usage_limit_total')->nullable(); // Max uses across all customers (null = unlimited)
            $table->unsignedInteger('usage_limit_per_customer')->nullable(); // Max uses for a single customer (null = unlimited)
            $table->unsignedInteger('total_usage_count')->default(0); // **KEY ADDITION**: Tracks total uses for promotions without coupons.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_promotions');
    }
};
