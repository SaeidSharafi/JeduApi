<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name');
            $table->text('description')->nullable();
            $table->text('default_teacher_info')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->enum('status', App\Enums\CourseStatusEnum::getAllValues())->default('active');
            $table->foreignId('created_by')->nullable()->constrained('admins', 'id')->nullOnDelete();
            $table->timestamps();
        });
    }
};
