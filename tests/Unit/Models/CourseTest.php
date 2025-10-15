<?php

declare(strict_types=1);

use App\Enums\System\MorphTypeEnum;

test('to array', function (): void {
    $course = App\Models\Course::factory()->create()->fresh();

    expect($course->toArray())
        ->toEqual([
            'id'                           => $course->id,
            'slug'                         => $course->slug,
            'full_name'                    => $course->full_name,
            'short_name'                   => $course->short_name,
            'description'                  => $course->description,
            'thumbnail_url'                => $course->thumbnail_url,
            'sample_certificate_image_url' => $course->sample_certificate_image_url,
            'duration'                     => $course->duration,
            'difficulty_level'             => $course->difficulty_level->value,
            'career_prospects_text'        => $course->career_prospects_text,
            'curriculum_summary_text'      => $course->curriculum_summary_text,
            'outcomes_json'                => $course->outcomes_json,
            'default_teacher_info'         => $course->default_teacher_info,
            'provides_certificate'         => $course->provides_certificate,
            'faq'                          => $course->faq,
            'additional_info'              => $course->additional_info,
            'meta_title'                   => $course->meta_title,
            'meta_description'             => $course->meta_description,
            'meta_keywords'                => $course->meta_keywords,
            'properties'                   => $course->properties,
            'status'                       => $course->status->value,
            'created_by'                   => $course->created_by,
            'review_count'                 => $course->review_count,
            'average_rating'               => $course->average_rating,
            'created_at'                   => $course->created_at?->utc()->toJSON(),
            'updated_at'                   => $course->updated_at?->utc()->toJSON(),

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
    $course  = App\Models\Course::factory()->create();
    $product = App\Models\Product::factory()->create([
        'productable_id'   => $course->id,
        'productable_type' => \App\Enums\Product\ProductableEnum::COURSE->value,
    ]);

    expect($course->products)
        ->toHaveCount(1)
        ->and($course->products->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($course->products->first()->id)
        ->toEqual($product->id);
});

test('relation digitalAssets', function (): void {
    $course       = App\Models\Course::factory()->create();
    $digitalAsset = App\Models\DigitalAsset::factory()->create();
    $course->digitalAssets()->attach($digitalAsset->id);

    expect($course->digitalAssets)
        ->toHaveCount(1)
        ->and($course->digitalAssets->first())
        ->toBeInstanceOf(App\Models\DigitalAsset::class)
        ->and($course->digitalAssets->first()->id)
        ->toEqual($digitalAsset->id);

    $digitalAssets = App\Models\DigitalAsset::factory()->count(3)->create();
    $course->digitalAssets()->sync($digitalAssets);
    $course->refresh();
    expect($course->digitalAssets)
        ->toHaveCount(3);
});

test('blogPosts relation', function (): void {
    $course   = App\Models\Course::factory()->create();
    $blogPost = App\Models\Blog\BlogPost::factory()->create();
    $course->blogPosts()->attach($blogPost->id);

    expect($course->blogPosts)
        ->toHaveCount(1)
        ->and($course->blogPosts->first())
        ->toBeInstanceOf(App\Models\Blog\BlogPost::class)
        ->and($course->blogPosts->first()->id)
        ->toEqual($blogPost->id);

    $blogPosts = App\Models\Blog\BlogPost::factory()->count(3)->create();
    $course->blogPosts()->sync($blogPosts);
    $course->refresh();
    expect($course->blogPosts)
        ->toHaveCount(3);
});

test('with reviews', function (): void {
    $course = App\Models\Course::factory()->create();
    $user   = App\Models\User::factory()->create();
    $review = App\Models\Review::factory()->create([
        'user_id'         => $user->id,
        'reviewable_id'   => $course->id,
        'reviewable_type' => MorphTypeEnum::COURSE->value,
    ]);

    $courseWithReviews = App\Models\Course::with('reviews')->find($course->id);
    expect($courseWithReviews?->reviews)
        ->toHaveCount(1)
        ->and($courseWithReviews?->reviews->first())
        ->toBeInstanceOf(App\Models\Review::class)
        ->and($courseWithReviews?->reviews->first()->id)
        ->toEqual($review->id);
});
