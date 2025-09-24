<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_page_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->string('location');
            $table->json('content');
            $table->unsignedInteger('order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_page_blocks');
    }
};
