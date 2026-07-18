<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_promotion_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('discount_promotion_id')->index()->constrained()->onDelete('cascade');

            // The 'IF' (condition) or the 'THEN' (action)
            $table->string('type');

            // The key for the handler class that contains the logic
            $table->string('handler');
            $table->jsonb('configuration');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_promotion_rules');
    }
};
