<?php

declare(strict_types=1);

use App\Enums\System\MorphTypeEnum;

test('to array', function (): void {
    $digitalAsset = App\Models\DigitalAsset::factory()->create()->fresh();

    expect($digitalAsset->toArray())
        ->toEqual([
            'id'                      => $digitalAsset->id,
            'name'                    => $digitalAsset->name,
            'slug'                    => $digitalAsset->slug,
            'description'             => $digitalAsset->description,
            'thumbnail_url'           => $digitalAsset->thumbnail_url,
            'version'                 => $digitalAsset->version,
            'page_count'              => $digitalAsset->page_count,
            'duration_seconds'        => $digitalAsset->duration_seconds,
            'keywords'                => $digitalAsset->keywords,
            'meta_title'              => $digitalAsset->meta_title,
            'meta_description'        => $digitalAsset->meta_description,
            'meta_keywords'           => $digitalAsset->meta_keywords,
            'is_attachable_to_course' => $digitalAsset->is_attachable_to_course,
            'status'                  => $digitalAsset->status->value,
            'published_at'            => $digitalAsset->published_at?->utc()->toJSON(),
            'created_by'              => $digitalAsset->created_by,
            'review_count'            => $digitalAsset->review_count,
            'average_rating'          => $digitalAsset->average_rating,
            'created_at'              => $digitalAsset->created_at?->utc()?->toJSON(),
            'updated_at'              => $digitalAsset->updated_at?->utc()?->toJSON(),
        ])->toBeArray();

});

test('relation categories', function (): void {
    $digitalAsset = App\Models\DigitalAsset::factory()->create();
    $category     = App\Models\Category::factory()->create();
    $digitalAsset->categories()->attach($category->id);

    expect($digitalAsset->categories)
        ->toHaveCount(1)
        ->and($digitalAsset->categories->first())
        ->toBeInstanceOf(App\Models\Category::class)
        ->and($digitalAsset->categories->first()->id)
        ->toEqual($category->id);

    $categories = App\Models\Category::factory()->count(3)->create();
    $digitalAsset->categories()->sync($categories);
    $digitalAsset->refresh();
    expect($digitalAsset->categories)
        ->toHaveCount(3);
});

test('relation courses', function (): void {
    $digitalAsset = App\Models\DigitalAsset::factory()->create();
    $course       = App\Models\Course::factory()->create();
    $digitalAsset->courses()->attach($course->id);

    expect($digitalAsset->courses)
        ->toHaveCount(1)
        ->and($digitalAsset->courses->first())
        ->toBeInstanceOf(App\Models\Course::class)
        ->and($digitalAsset->courses->first()->id)
        ->toEqual($course->id);

    $courses = App\Models\Course::factory()->count(3)->create();
    $digitalAsset->courses()->sync($courses);
    $digitalAsset->refresh();
    expect($digitalAsset->courses)
        ->toHaveCount(3);
});

test('with reviews', function (): void {
    $digitalAsset = App\Models\DigitalAsset::factory()->create();
    $user1        = App\Models\User::factory()->create();
    $user2        = App\Models\User::factory()->create();

    $review1 = App\Models\Review::factory()->create([
        'user_id'         => $user1->id,
        'reviewable_id'   => $digitalAsset->id,
        'reviewable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'rating'          => 4,
        'status'          => \App\Enums\Content\ReviewStatusEnum::APPROVED,
    ]);

    $review2 = App\Models\Review::factory()->create([
        'user_id'         => $user2->id,
        'reviewable_id'   => $digitalAsset->id,
        'reviewable_type' => MorphTypeEnum::DIGITAL_ASSET->value,
        'rating'          => 5,
        'status'          => \App\Enums\Content\ReviewStatusEnum::PENDING,
    ]);

    $digitalAsset->refresh();

    expect($digitalAsset->reviews)
        ->toHaveCount(2)
        ->and($digitalAsset->reviews->first())
        ->toBeInstanceOf(App\Models\Review::class)
        ->and($digitalAsset->reviews->pluck('id')->toArray())
        ->toEqualCanonicalizing([$review1->id, $review2->id]);

});
