<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorizables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unsignedBigInteger('categorizable_id');
            $table->string('categorizable_type');
            $table->boolean('good_for_start')->default(false);
            $table->primary(['category_id', 'categorizable_id', 'categorizable_type'], 'categorizables_primary');
            $table->index(['categorizable_id', 'categorizable_type'], 'categorizable_index');
        });
    }
};
