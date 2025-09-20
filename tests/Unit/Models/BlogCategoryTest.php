<?php
it('to array', function (): void {
    $blogCategory = App\Models\Blog\BlogCategory::factory()->create()->fresh();

    $array = $blogCategory->toArray();
    expect($array)
        ->toEqual([
            'id'          => $blogCategory->id,
            'name'        => $blogCategory->name,
            'slug'        => $blogCategory->slug,
            'description' => $blogCategory->description,
            'parent_id'   => $blogCategory->parent_id,
            'icon'        => $blogCategory->icon,
            'meta_title'       => $blogCategory->meta_title,
            'meta_description' => $blogCategory->meta_description,
            'meta_keywords'    => $blogCategory->meta_keywords,
            'created_at'  => $blogCategory->created_at?->utc()->toJSON(),
            'updated_at'  => $blogCategory->updated_at?->utc()->toJSON(),
        ]);
});

it('relation parent category', function (): void {
    $parentCategory = App\Models\Blog\BlogCategory::factory()->create();
    $childCategory  = App\Models\Blog\BlogCategory::factory()->create(['parent_id' => $parentCategory->id]);

    expect($childCategory->parent)
        ->toBeInstanceOf(App\Models\Blog\BlogCategory::class)
        ->and($childCategory->parent->id)
        ->toEqual($parentCategory->id);
});

it('relation child categories', function (): void {
    $parentCategory = App\Models\Blog\BlogCategory::factory()->create();
    $childCategories = App\Models\Blog\BlogCategory::factory()->count(3)->create(['parent_id' => $parentCategory->id]);

    expect($parentCategory->children)
        ->toHaveCount(3)
        ->and($parentCategory->children->first())
        ->toBeInstanceOf(App\Models\Blog\BlogCategory::class)
        ->and($parentCategory->children->pluck('id')->toArray())
        ->toEqual($childCategories->pluck('id')->toArray());
});

it('relation posts', function (): void {
    $blogCategory = App\Models\Blog\BlogCategory::factory()->create();
    $blogPost     = App\Models\Blog\BlogPost::factory()->create();
    $blogCategory->posts()->attach($blogPost->id);

    expect($blogCategory->posts)
        ->toHaveCount(1)
        ->and($blogCategory->posts->first())
        ->toBeInstanceOf(App\Models\Blog\BlogPost::class)
        ->and($blogCategory->posts->first()->id)
        ->toEqual($blogPost->id);

    $blogPosts = App\Models\Blog\BlogPost::factory()->count(3)->create();
    $blogCategory->posts()->sync($blogPosts);
    $blogCategory->refresh();
    expect($blogCategory->posts)
        ->toHaveCount(3);
});



