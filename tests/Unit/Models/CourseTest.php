<?php

declare(strict_types=1);

test('to array', function (): void {
    $course = App\Models\Course::factory()->create()->fresh();

    expect($course->toArray())
        ->toEqual([
            'id' => $course->id,
            'slug' => $course->slug,
            'full_name' => $course->full_name,
            'short_name' => $course->short_name,
            'description' => $course->description,
            'sample_certificate_image_url' => $course->sample_certificate_image_url,
            'duration' => $course->duration,
            'difficulty_level' => $course->difficulty_level->value,
            'career_prospects_text' => $course->career_prospects_text,
            'curriculum_summary_text' => $course->curriculum_summary_text,
            'outcomes_json' => $course->outcomes_json,
            'default_teacher_info' => $course->default_teacher_info,
            'additional_info' => $course->additional_info,
            'meta_title' => $course->meta_title,
            'meta_description' => $course->meta_description,
            'meta_keywords' => $course->meta_keywords,
            'properties' => $course->properties,
            'status' => $course->status->value,
            'created_by' => $course->created_by,
            'created_at' => $course->created_at->toISOString(),
            'updated_at' => $course->updated_at->toISOString(),

        ]);

});
