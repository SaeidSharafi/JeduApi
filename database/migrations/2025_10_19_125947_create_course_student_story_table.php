<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_student_story', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id');
            $table
                ->foreign('course_id', 'cs_story_course_id_foreign')
                ->references('id')->on('courses')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('student_story_id');
            $table->foreign('student_story_id', 'cs_story_story_id_foreign')->references('id')->on('student_stories')->cascadeOnDelete();
            $table->primary(['course_id', 'student_story_id'], 'cs_story_course_id_story_id_primary');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_student_story');
    }
};
