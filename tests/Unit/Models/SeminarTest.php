<?php

declare(strict_types=1);

test('to array', function (): void {
    $seminar = App\Models\Seminar::factory()->create()->fresh();

    expect($seminar->toArray())
        ->toEqual([
            'id'                       => $seminar->id,
            'slug'                     => $seminar->slug,
            'full_name'                => $seminar->full_name,
            'short_name'               => $seminar->short_name,
            'subtitle'                 => $seminar->subtitle,
            'learning_objectives'      => $seminar->learning_objectives,
            'target_audience'          => $seminar->target_audience,
            'prerequisites'            => $seminar->prerequisites,
            'promo_video_external_url' => $seminar->promo_video_external_url,
            'estimated_duration_desc'  => $seminar->estimated_duration_desc,
            'level'                    => $seminar->level->value,
            'provides_certificate'     => $seminar->provides_certificate,
            'faq'                      => $seminar->faq,
            'keywords'                 => $seminar->keywords,
            'description'              => $seminar->description,
            'meta_title'               => $seminar->meta_title,
            'meta_description'         => $seminar->meta_description,
            'meta_keywords'            => $seminar->meta_keywords,
            'status'                   => $seminar->status->value,
            'created_by'               => $seminar->created_by,
            'created_at'               => $seminar->created_at?->utc()->toJSON(),
            'updated_at'               => $seminar->updated_at?->utc()->toJSON(),
        ]);

});

test('relation categories', function (): void {
    $seminar  = App\Models\Seminar::factory()->create();
    $category = App\Models\Category::factory()->create();
    $seminar->categories()->attach($category->id);

    expect($seminar->categories)
        ->toHaveCount(1)
        ->and($seminar->categories->first())
        ->toBeInstanceOf(App\Models\Category::class)
        ->and($seminar->categories->first()->id)
        ->toEqual($category->id);

    $categories = App\Models\Category::factory()->count(3)->create();
    $seminar->categories()->sync($categories);
    $seminar->refresh();
    expect($seminar->categories)
        ->toHaveCount(3);
});

test('relation digital assets', function (): void {
    $seminar      = App\Models\Seminar::factory()->create();
    $digitalAsset = App\Models\DigitalAsset::factory()->create();
    $seminar->digitalAssets()->attach($digitalAsset->id);

    expect($seminar->digitalAssets)
        ->toHaveCount(1)
        ->and($seminar->digitalAssets->first())
        ->toBeInstanceOf(App\Models\DigitalAsset::class)
        ->and($seminar->digitalAssets->first()->id)
        ->toEqual($digitalAsset->id);

    $digitalAssets = App\Models\DigitalAsset::factory()->count(3)->create();
    $seminar->digitalAssets()->sync($digitalAssets);
    $seminar->refresh();
    expect($seminar->digitalAssets)
        ->toHaveCount(3);
});

test('relation auditor', function (): void {
    $seminar             = App\Models\Seminar::factory()->create();
    $auditor             = App\Models\Staff::factory()->create();
    $anotherAuditor      = App\Models\Staff::factory()->create();
    $seminar->created_by = $auditor->id;
    $seminar->save();
    $seminar->refresh();
    expect($seminar->auditor->id)
        ->toBe($auditor->id)
        ->and($seminar->auditor->id)
        ->not()->toBe($anotherAuditor->id);

});

test('relation products', function (): void {
    $seminar = App\Models\Seminar::factory()->create();
    $product = App\Models\Product::factory()->create([
        'productable_id'   => $seminar->id,
        'productable_type' => App\Enums\ProductableEnum::SEMINAR->value,
    ]);

    expect($seminar->products)
        ->toHaveCount(1)
        ->and($seminar->products->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($seminar->products->first()->id)
        ->toEqual($product->id);
});

test('relation blog posts', function (): void {
    $seminar  = App\Models\Seminar::factory()->create();
    $blogPost = App\Models\Blog\BlogPost::factory()->create();
    $seminar->blogPosts()->attach($blogPost->id);
    expect($seminar->blogPosts)
        ->toHaveCount(1)
        ->and($seminar->blogPosts->first())
        ->toBeInstanceOf(App\Models\Blog\BlogPost::class)
        ->and($seminar->blogPosts->first()->id)
        ->toEqual($blogPost->id);
});
