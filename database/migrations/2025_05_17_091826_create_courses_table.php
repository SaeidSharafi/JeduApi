<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
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
            $table->string('status')->index()->default(PublicationStatusEnum::DRAFT->value);
            $table->string('full_name');
            $table->string('short_name')->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('sample_certificate_image_url')->nullable();
            $table->integer('duration')->nullable();
            $table->string('difficulty_level');
            $table->text('career_prospects_text')->nullable();
            $table->text('curriculum_summary_text')->nullable(); // e.g., "1 فصل، 20 درس، 4 تمرین"
            $table->json('outcomes_json')->nullable(); // What students will learn
            $table->text('default_teacher_info')->nullable(); // Fallback teacher info
            $table->boolean('provides_certificate')->default(false);
            $table->json('faq')->nullable(); // Frequently Asked Questions
            $table->json('additional_info')->nullable(); // For any other structured course-specific info
            $this->addMetaTagColumns($table);
            $table->json('properties')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff', 'id')->nullOnDelete();
            $table->integer('review_count')->default(0);
            $table->decimal('average_rating', 3)->default(0.0);
            $table->timestamps();
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->fullText(['full_name', 'short_name', 'slug', 'description'], 'courses_fulltext_index');
            }
        });
        Schema::table('courses', function (Blueprint $table) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('CREATE EXTENSION IF NOT EXISTS pgroonga');
                DB::statement('CREATE INDEX courses_pgroonga_index ON courses USING pgroonga (full_name, short_name, slug, description)');
            }
        });
    }
};
