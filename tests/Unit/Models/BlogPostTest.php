<?php
it('to array', function (): void {
    $blogPost = App\Models\Blog\BlogPost::factory()->create()->fresh();

    expect($blogPost->toArray())
        ->toEqual([
            'id'                 => $blogPost->id,
            'title'              => $blogPost->title,
            'slug'               => $blogPost->slug,
            'body'               => $blogPost->body,
            'excerpt'           => $blogPost->excerpt,
            'author_id'         => $blogPost->author_id,
            'status'            => $blogPost->status->value,
            'published_at'      => $blogPost->published_at?->utc()->toJSON(),
            'read_time_minutes' => $blogPost->read_time_minutes,
            'is_featured'       => $blogPost->is_featured,
            'main_productable_id'   => $blogPost->main_productable_id,
            'main_productable_type' => $blogPost->main_productable_type,
            'created_at'        => $blogPost->created_at?->utc()->toJSON(),
            'updated_at'        => $blogPost->updated_at?->utc()->toJSON(),
        ]);
});

it('relation author', function (): void {
    $blogPost = App\Models\Blog\BlogPost::factory()->create();
    $author   = App\Models\Staff::factory()->create();
    $blogPost->author()->associate($author);
    $blogPost->save();
    $blogPost->refresh();

    expect($blogPost->author)
        ->toBeInstanceOf(App\Models\Staff::class)
        ->and($blogPost->author->id)
        ->toEqual($author->id);
});

it('relation categories', function (): void {
    $blogPost = App\Models\Blog\BlogPost::factory()->create();
    $category = App\Models\Blog\BlogCategory::factory()->create();
    $blogPost->categories()->attach($category->id);

    expect($blogPost->categories)
        ->toHaveCount(1)
        ->and($blogPost->categories->first())
        ->toBeInstanceOf(App\Models\Blog\BlogCategory::class)
        ->and($blogPost->categories->first()->id)
        ->toEqual($category->id);

    $categories = App\Models\Blog\BlogCategory::factory()->count(3)->create();
    $blogPost->categories()->sync($categories);
    $blogPost->refresh();
    expect($blogPost->categories)
        ->toHaveCount(3);
});

it('relation courses', function (): void {
    $blogPost = App\Models\Blog\BlogPost::factory()->create();
    $course   = App\Models\Course::factory()->create();
    $blogPost->courses()->attach($course->id);

    expect($blogPost->courses)
        ->toHaveCount(1)
        ->and($blogPost->courses->first())
        ->toBeInstanceOf(App\Models\Course::class)
        ->and($blogPost->courses->first()->id)
        ->toEqual($course->id);

    $courses = App\Models\Course::factory()->count(3)->create();
    $blogPost->courses()->sync($courses);
    $blogPost->refresh();
    expect($blogPost->courses)
        ->toHaveCount(3);
});

it('relation seminars', function (): void {
    $blogPost = App\Models\Blog\BlogPost::factory()->create();
    $seminar  = App\Models\Seminar::factory()->create();
    $blogPost->seminars()->attach($seminar->id);

    expect($blogPost->seminars)
        ->toHaveCount(1)
        ->and($blogPost->seminars->first())
        ->toBeInstanceOf(App\Models\Seminar::class)
        ->and($blogPost->seminars->first()->id)
        ->toEqual($seminar->id);

    $seminars = App\Models\Seminar::factory()->count(3)->create();
    $blogPost->seminars()->sync($seminars);
    $blogPost->refresh();
    expect($blogPost->seminars)
        ->toHaveCount(3);
});

it('relation digitalAssets', function (): void {
    $blogPost     = App\Models\Blog\BlogPost::factory()->create();
    $digitalAsset = App\Models\DigitalAsset::factory()->create();
    $blogPost->digitalAssets()->attach($digitalAsset->id);

    expect($blogPost->digitalAssets)
        ->toHaveCount(1)
        ->and($blogPost->digitalAssets->first())
        ->toBeInstanceOf(App\Models\DigitalAsset::class)
        ->and($blogPost->digitalAssets->first()->id)
        ->toEqual($digitalAsset->id);

    $digitalAssets = App\Models\DigitalAsset::factory()->count(3)->create();
    $blogPost->digitalAssets()->sync($digitalAssets);
    $blogPost->refresh();
    expect($blogPost->digitalAssets)
        ->toHaveCount(3);
});


it('relation main productable', function (): void {
    $blogPost = App\Models\Blog\BlogPost::factory()->create();
    $course   = App\Models\Course::factory()->create();
    $blogPost->mainProductable()->associate($course);
    $blogPost->save();
    $blogPost->refresh();

    expect($blogPost->mainProductable)
        ->toBeInstanceOf(App\Models\Course::class)
        ->and($blogPost->mainProductable->id)
        ->toEqual($course->id);

    $seminar = App\Models\Seminar::factory()->create();
    $blogPost->mainProductable()->associate($seminar);
    $blogPost->save();
    $blogPost->refresh();

    expect($blogPost->mainProductable)
        ->toBeInstanceOf(App\Models\Seminar::class)
        ->and($blogPost->mainProductable->id)
        ->toEqual($seminar->id);

    $digitalAsset = App\Models\DigitalAsset::factory()->create();
    $blogPost->mainProductable()->associate($digitalAsset);
    $blogPost->save();
    $blogPost->refresh();

    expect($blogPost->mainProductable)
        ->toBeInstanceOf(App\Models\DigitalAsset::class)
        ->and($blogPost->mainProductable->id)
        ->toEqual($digitalAsset->id);
});

it('relation related productables', function (): void {
    $blogPost = App\Models\Blog\BlogPost::factory()->create()->fresh();

    $course       = App\Models\Course::factory()->create();
    $seminar      = App\Models\Seminar::factory()->create();
    $digitalAsset = App\Models\DigitalAsset::factory()->create();

    $blogPost->courses()->attach($course->id);
    $blogPost->seminars()->attach($seminar->id);
    $blogPost->digitalAssets()->attach($digitalAsset->id);

    $blogPost->loadRelatedproductables();

    expect($blogPost->relatedProductables)
        ->toHaveCount(3)
        ->and($blogPost->relatedProductables->whereInstanceOf(App\Models\Course::class)->first()->id)
        ->toEqual($course->id)
        ->and($blogPost->relatedProductables->whereInstanceOf(App\Models\Seminar::class)->first()->id)
        ->toEqual($seminar->id)
        ->and($blogPost->relatedProductables->whereInstanceOf(App\Models\DigitalAsset::class)->first()->id)
        ->toEqual($digitalAsset->id);

    // Test syncRelatedProductables
    $anotherCourse = App\Models\Course::factory()->create();
    $blogPost->syncRelatedProductables([
        ['type' => 'course', 'id' => $anotherCourse->id],
    ]);
    $blogPost->refresh();
    $blogPost->loadRelatedproductables();

    expect($blogPost->relatedProductables)
        ->toHaveCount(1)
        ->and($blogPost->relatedProductables->first())
        ->toBeInstanceOf(App\Models\Course::class)
        ->and($blogPost->relatedProductables->first()->id)
        ->toEqual($anotherCourse->id);
});

it('syncRelatedProductables with empty array detaches all', function (): void {
    $blogPost = App\Models\Blog\BlogPost::factory()->create();

    $course       = App\Models\Course::factory()->create();
    $seminar      = App\Models\Seminar::factory()->create();
    $digitalAsset = App\Models\DigitalAsset::factory()->create();

    $blogPost->courses()->attach($course->id);
    $blogPost->seminars()->attach($seminar->id);
    $blogPost->digitalAssets()->attach($digitalAsset->id);

    $blogPost->loadRelatedproductables();

    expect($blogPost->relatedProductables)
        ->toHaveCount(3);

    // Now sync with empty array
    $blogPost->syncRelatedProductables([]);
    $blogPost->refresh();
    $blogPost->loadRelatedproductables();

    expect($blogPost->relatedProductables)
        ->toHaveCount(0);
});

it('relation reviews', function (): void {
    $blogPost = App\Models\Blog\BlogPost::factory()->create();
    $review = App\Models\Review::factory()->create([
        'reviewable_id'   => $blogPost->id,
        'reviewable_type' => \App\Enums\MorphTypeEnum::BLOG_POST,
    ]);

    expect($blogPost->reviews)
        ->toHaveCount(1)
        ->and($blogPost->reviews->first())
        ->toBeInstanceOf(App\Models\Review::class)
        ->and($blogPost->reviews->first()->id)
        ->toEqual($review->id);

});
