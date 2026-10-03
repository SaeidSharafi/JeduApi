<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Course;
use App\Models\Product;

test('to Array', function (): void {
    $category = Category::factory()->create();
    $product  = Product::factory()->create();
    $product->categories()->attach($category->id);
    $categorizbale = App\Models\Categorizable::first();
    expect($categorizbale)->toBeInstanceOf(App\Models\Categorizable::class)
        ->and($categorizbale->toArray())
        ->toEqual([
            'id'                 => $categorizbale->id,
            'category_id'        => $category->id,
            'categorizable_id'   => $product->id,
            'categorizable_type' => App\Enums\System\MorphTypeEnum::PRODUCT->value,
            'good_for_start'     => false,
        ]);
});
test('categorizable realtionship', function (): void {
    $category = Category::factory()->create();
    $course   = Course::factory()->create();
    $course->categories()->attach($category->id);
    $categorizable = App\Models\Categorizable::first();
    expect($categorizable)->toBeInstanceOf(App\Models\Categorizable::class)
        ->and($categorizable->categorizable)
        ->toBeInstanceOf(Course::class)
        ->and($categorizable->categorizable->id)
        ->toEqual($course->id);
});
