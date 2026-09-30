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
        Schema::create('learning_path_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('productable_type');
            $table->unsignedBigInteger('productable_id');
            $table->string('title');
            $table->text('description');
            $table->timestamps();

            $table->unique(['learning_path_id', 'position']);
            $table->unique(['learning_path_id', 'productable_type', 'productable_id']);
            $table->index(['productable_type', 'productable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_path_steps');
    }
};
