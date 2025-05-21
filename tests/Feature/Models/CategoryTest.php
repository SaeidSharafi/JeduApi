<?php

declare(strict_types=1);

use App\Models\Category;

test('to Array', function () {
    $category = Category::factory()->create();

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->toArray())
        ->toEqual([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $category->image_url,
            'icon_url' => $category->icon_url,
            'color_scheme' => $category->color_scheme,
            'meta_title' => $category->meta_title,
            'meta_description' => $category->meta_description,
            'meta_keywords' => $category->meta_keywords,
            'properties' => $category->properties,
            'additional_info' => $category->additional_info,
            'status' => $category->status->value,
            'created_by' => $category->created_by,
            'created_at' => $category->created_at->toISOString(),
            'updated_at' => $category->updated_at->toISOString(),
        ]);
});
