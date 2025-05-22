<?php

declare(strict_types=1);

use App\Data\Category\CategoryListItemData;

it('create CategoryListItemData from Category', function (): void {
    $category = App\Models\Category::factory()->create()->fresh();

    $categoryListItemData = CategoryListItemData::from($category);

    expect($categoryListItemData->toArray())
        ->toEqual([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'status' => [
                'value' => $category->status->value,
                'label' => $category->status->translate(),
            ],
            'image_url' => $category->image_url,
            'icon_url' => $category->icon_url,
            'created_by' => $category->created_by,
            'created_at' => (string) $category->created_at,
            'updated_at' => (string) $category->updated_at,
        ]);
});
