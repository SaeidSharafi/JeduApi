<?php

declare(strict_types=1);

test('to array', function (): void {
    $course = App\Models\Course::factory()->create()->fresh();

    expect($course->toArray())
        ->toEqual([
            'id'                           => $course->id,
            'slug'                         => $course->slug,
            'full_name'                    => $course->full_name,
            'short_name'                   => $course->short_name,
            'description'                  => $course->description,
            'sample_certificate_image_url' => $course->sample_certificate_image_url,
            'duration'                     => $course->duration,
            'difficulty_level'             => $course->difficulty_level->value,
            'career_prospects_text'        => $course->career_prospects_text,
            'curriculum_summary_text'      => $course->curriculum_summary_text,
            'outcomes_json'                => $course->outcomes_json,
            'default_teacher_info'         => $course->default_teacher_info,
            'additional_info'              => $course->additional_info,
            'meta_title'                   => $course->meta_title,
            'meta_description'             => $course->meta_description,
            'meta_keywords'                => $course->meta_keywords,
            'properties'                   => $course->properties,
            'status'                       => $course->status->value,
            'created_by'                   => $course->created_by,
            'created_at'                   => $course->created_at->format('Y-m-d H:i:s'),
            'updated_at'                   => $course->updated_at->format('Y-m-d H:i:s'),

        ]);

});

test('relation categories', function (): void {
    $course   = App\Models\Course::factory()->create();
    $category = App\Models\Category::factory()->create();
    $course->categories()->attach($category->id);

    expect($course->categories)
        ->toHaveCount(1)
        ->and($course->categories->first())
        ->toBeInstanceOf(App\Models\Category::class)
        ->and($course->categories->first()->id)
        ->toEqual($category->id);

    $categories = App\Models\Category::factory()->count(3)->create();
    $course->categories()->sync($categories);
    $course->refresh();
    expect($course->categories)
        ->toHaveCount(3);
});
test('relation products', function (): void {
    $course = App\Models\Course::factory()->create();
    $product = App\Models\Product::factory()->create([
        'productable_id' => $course->id,
        'productable_type' => App\Enums\ProductableEnum::COURSE->value,
    ]);

    expect($course->products)
        ->toHaveCount(1)
        ->and($course->products->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($course->products->first()->id)
        ->toEqual($product->id);
});
