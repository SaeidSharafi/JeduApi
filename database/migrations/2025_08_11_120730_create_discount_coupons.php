<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('discount_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_promotion_id')->constrained()->onDelete('cascade');

            // The actual code, e.g., 'WELCOME10'. Must be unique and is indexed for fast lookups.
            $table->string('code')->unique();

            // Usage limits specific to this code
            $table->unsignedInteger('usage_limit')->nullable(); // Max uses (null = unlimited)
            $table->unsignedInteger('usage_count')->default(0);


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_coupons');
    }
};
