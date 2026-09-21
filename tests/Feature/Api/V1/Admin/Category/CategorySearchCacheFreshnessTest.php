<?php

declare(strict_types=1);

use App\Contracts\Cache\CacheStore;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\System\CacheKey;
use App\Models\Category;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('clears cached search results and suggestions when a category slug changes', function (): void {
    $this->authorized_user([PermissionEnum::CATEGORY_UPDATE->value]);
    $category = Category::factory()->create(['slug' => 'programming']);

    $cache = app(CacheStore::class);
    $cache->put(CacheKey::Search, ['hash' => 'query-hash'], ['stale']);
    $cache->put(CacheKey::SearchSuggest, ['hash' => 'suggest-hash'], ['stale']);

    $this->putJson(route('api.v1.admin.categories.update', ['category' => $category->id]), [
        'name'             => 'Category',
        'slug'             => 'programming-updated',
        'status'           => PublicationStatusEnum::PUBLISHED->value,
        'parent_id'        => null,
        'description'      => null,
        'color_scheme'     => null,
        'meta_title'       => 'Meta title',
        'meta_description' => 'Meta description lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        'meta_keywords'    => null,
        'properties'       => null,
        'additional_info'  => null,
        'media'            => [],
    ])->assertStatus(200);

    expect($cache->get(CacheKey::Search, ['hash' => 'query-hash']))->toBeNull()
        ->and($cache->get(CacheKey::SearchSuggest, ['hash' => 'suggest-hash']))->toBeNull();
});
