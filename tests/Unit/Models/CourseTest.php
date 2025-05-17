<?php

test('to array', function () {
    $course = \App\Models\Course::factory()->create()->fresh();

    expect($course->toArray())
        ->toEqual([
            'id'                  => $course->id,
            'slug'                 => $course->slug,
            'name'                 => $course->name,
            'short_name'           => $course->short_name,
            'description'          => $course->description,
            'default_teahcer_info' => $course->default_teahcer_info,
            'meta_title'           => $course->meta_title,
            'meta_description'     => $course->meta_description,
            'meta_keywords'        => $course->meta_keywords,
            'status'               => $course->status->value,
            'created_by'           => $course->created_by,
            'created_at'           => $course->created_at->toISOString(),
            'updated_at'           => $course->updated_at->toISOString(),
        ]);

});
