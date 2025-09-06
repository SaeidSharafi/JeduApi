<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;

test('to Array', function (): void {
    $category = Category::factory()->create();

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->toArray())
        ->toEqual([
            'id'               => $category->id,
            'name'             => $category->name,
            'slug'             => $category->slug,
            'description'      => $category->description,
            'image_url'        => $category->image_url,
            'icon_url'         => $category->icon_url,
            'color_scheme'     => $category->color_scheme,
            'meta_title'       => $category->meta_title,
            'meta_description' => $category->meta_description,
            'meta_keywords'    => $category->meta_keywords,
            'properties'       => $category->properties,
            'additional_info'  => $category->additional_info,
            'status'           => $category->status->value,
            'created_by'       => $category->created_by,
            'created_at'       => $category->created_at?->utc()->toISOString(),
            'updated_at'       => $category->updated_at?->utc()->toISOString(),
        ]);
});

test('relation categorizable for Courses', function (): void {
    $category = Category::factory()->create();
    $course   = Course::factory()->create();
    $category->courses()->attach($course);

    expect($category->courses)
        ->toHaveCount(1)
        ->and($category->courses->first())
        ->toBeInstanceOf(Course::class)
        ->and($category->courses->first()->id)
        ->toEqual($course->id);
});

test('relation categorizable for Digital Assets', function (): void {
    $category = Category::factory()->create();
    $asset    = DigitalAsset::factory()->create();
    $category->digitalAssets()->attach($asset);

    expect($category->digitalAssets)
        ->toHaveCount(1)
        ->and($category->digitalAssets->first())
        ->toBeInstanceOf(DigitalAsset::class)
        ->and($category->digitalAssets->first()->id)
        ->toEqual($asset->id);
});
test('relation categorizable for seminars', function (): void {
    $category = Category::factory()->create();
    $seminar  = App\Models\Seminar::factory()->create();
    $category->seminars()->attach($seminar);

    expect($category->seminars)
        ->toHaveCount(1)
        ->and($category->seminars->first())
        ->toBeInstanceOf(App\Models\Seminar::class)
        ->and($category->seminars->first()->id)
        ->toEqual($seminar->id);
});
test('relation categorizable', function (): void {
    $category = Category::factory()->create();
    $seminar  = App\Models\Seminar::factory()->create();
    $course   = Course::factory()->create();
    $category->seminars()->attach($seminar);
    $category->courses()->attach($course);

    expect($category->categorizable)
        ->toHaveCount(2);
});
test('relation product', function (): void {
    $category = Category::factory()->create();
    $product  = App\Models\Product::factory()->create()->fresh();
    $category->products()->attach($product);
    expect($category->products)
        ->toHaveCount(1)
        ->and($category->products->first())
        ->toBeInstanceOf(App\Models\Product::class)
        ->and($category->products->first()->id)
        ->toEqual($product->id);
});
