<?php

declare(strict_types=1);

test('to array', function (): void {
    $bundle = App\Models\Bundle::factory()->create()->fresh();

    expect($bundle->toArray())
        ->toEqual([
            'id'               => $bundle->id,
            'slug'             => $bundle->slug,
            'full_name'        => $bundle->full_name,
            'short_name'       => $bundle->short_name,
            'description'      => $bundle->description,
            'thumbnail_url'    => $bundle->thumbnail_url,
            'faq'              => $bundle->faq,
            'additional_info'  => $bundle->additional_info,
            'meta_title'       => $bundle->meta_title,
            'meta_description' => $bundle->meta_description,
            'meta_keywords'    => $bundle->meta_keywords,
            'properties'       => $bundle->properties,
            'status'           => $bundle->status->value,
            'created_by'       => $bundle->created_by,
            'created_at'       => $bundle->created_at?->utc()->toJSON(),
            'updated_at'       => $bundle->updated_at?->utc()->toJSON(),

        ]);

});

test('relation categories', function (): void {
    $bundle   = App\Models\Bundle::factory()->create();
    $category = App\Models\Category::factory()->create();
    $bundle->categories()->attach($category->id);

    expect($bundle->categories)
        ->toHaveCount(1)
        ->and($bundle->categories->first())
        ->toBeInstanceOf(App\Models\Category::class)
        ->and($bundle->categories->first()->id)
        ->toEqual($category->id);

    $categories = App\Models\Category::factory()->count(3)->create();
    $bundle->categories()->sync($categories);
    $bundle->refresh();
    expect($bundle->categories)
        ->toHaveCount(3);
});
test('relation products', function (): void {
    $bundle  = App\Models\Bundle::factory()->create();
    $product = App\Models\Product::factory()->create([
        'productable_id'   => $bundle->id,
        'productable_type' => App\Enums\Product\ProductableEnum::BUNDLE->value,
    ]);

    expect($bundle->products)
        ->toHaveCount(1)
        ->and($bundle->products->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($bundle->products->first()->id)
        ->toEqual($product->id);
});
