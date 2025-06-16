<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_delivery_option_teacher', function (Blueprint $table) {
            $table->unsignedBigInteger('product_delivery_option_id');
            $table
                ->foreign('product_delivery_option_id','pdo_teacher_pdo_id_foreign')
                ->references('id')->on('product_delivery_options')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
            $table->primary(['product_delivery_option_id', 'teacher_id'], 'pdo_teacher_pdo_id_teacher_id_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_teacher');
    }
};
