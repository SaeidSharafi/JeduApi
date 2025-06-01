<?php

declare(strict_types=1);

use App\Enums\PublicationStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use App\Traits\HasMetaTagsMigration;

    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->enum('status', PublicationStatusEnum::getAllValues())->default(PublicationStatusEnum::DRAFT->value);
            $table->string('full_name');
            $table->string('short_name')->nullable();
            $table->text('description')->nullable();
            $table->string('sample_certificate_image_url')->nullable();
            $table->integer('duration')->nullable();
            $table->enum('difficulty_level', App\Enums\CourseDifficultyLevelEnum::getAllValues());
            $table->text('career_prospects_text')->nullable();
            $table->text('curriculum_summary_text')->nullable(); // e.g., "1 فصل، 20 درس، 4 تمرین"
            $table->json('outcomes_json')->nullable(); // What students will learn
            $table->text('default_teacher_info')->nullable(); // Fallback teacher info
            $table->json('additional_info')->nullable(); // For any other structured course-specific info
            $this->addMetaTagColumns($table);
            $table->json('properties')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff', 'id')->nullOnDelete();
            $table->timestamps();
        });
    }
};
