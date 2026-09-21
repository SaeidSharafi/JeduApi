<?php

declare(strict_types=1);

use App\Actions\Admin\Bundle\UpdateBundleAction;
use App\Actions\Admin\Category\UpdateCategoryAction;
use App\Actions\Admin\DigitalAsset\UpdateDigitalAssetAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Bundle\BundleUpdateData;
use App\Data\Admin\Category\CreateCategoryData;
use App\Data\Admin\DigitalAsset\CreateDigitalAssetData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\System\CacheKey;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\DigitalAsset;

covers(
    UpdateCategoryAction::class,
    UpdateDigitalAssetAction::class,
    UpdateBundleAction::class,
);

$warmSearchCaches = function (): void {
    $cache = app(CacheStore::class);
    $cache->put(CacheKey::Search, ['hash' => 'query-hash'], ['stale']);
    $cache->put(CacheKey::SearchSuggest, ['hash' => 'suggest-hash'], ['stale']);
};

$assertSearchCachesCleared = function (): void {
    $cache = app(CacheStore::class);
    expect($cache->get(CacheKey::Search, ['hash' => 'query-hash']))->toBeNull()
        ->and($cache->get(CacheKey::SearchSuggest, ['hash' => 'suggest-hash']))->toBeNull();
};

test('a category slug change clears search results and suggestions', function () use ($warmSearchCaches, $assertSearchCachesCleared): void {
    $category = Category::factory()->create(['slug' => 'programming']);

    $warmSearchCaches();
    app(UpdateCategoryAction::class)->handle(CreateCategoryData::from([
        'name'             => 'Category',
        'slug'             => 'programming-updated',
        'status'           => PublicationStatusEnum::PUBLISHED->value,
        'parent_id'        => null,
        'description'      => null,
        'color_scheme'     => null,
        'meta_title'       => 'Meta title',
        'meta_description' => 'Meta description that is long enough to satisfy the validation rule of the DTO.',
        'meta_keywords'    => null,
        'properties'       => null,
        'additional_info'  => null,
        'media'            => [],
    ]), $category);

    $assertSearchCachesCleared();
});

test('a digital asset searchable-field change clears search', function () use ($warmSearchCaches, $assertSearchCachesCleared): void {
    $digitalAsset = DigitalAsset::factory()->create();

    $warmSearchCaches();
    app(UpdateDigitalAssetAction::class)->handle(CreateDigitalAssetData::from([
        'short_name'              => 'Updated asset',
        'full_name'               => 'Updated asset full name',
        'slug'                    => 'updated-asset-slug',
        'description'             => null,
        'version'                 => null,
        'is_attachable_to_course' => false,
        'difficulty_level'        => CourseDifficultyLevelEnum::BEGINNER->value,
        'status'                  => PublicationStatusEnum::PUBLISHED->value,
        'faq'                     => null,
        'created_by'              => null,
        'keywords'                => null,
        'meta_title'              => null,
        'meta_description'        => null,
        'meta_keywords'           => null,
        'published_at'            => null,
        'page_count'              => null,
        'duration_seconds'        => null,
        'categories'              => [],
        'attachments'             => [],
        'media'                   => [],
    ]), $digitalAsset);

    $assertSearchCachesCleared();
});

test('a bundle status change clears search', function () use ($warmSearchCaches, $assertSearchCachesCleared): void {
    $bundle = Bundle::factory()->create();

    $warmSearchCaches();
    app(UpdateBundleAction::class)->handle(BundleUpdateData::from([
        'full_name'   => 'Updated Bundle',
        'short_name'  => 'BND',
        'description' => 'Updated bundle description',
        'status'      => PublicationStatusEnum::PUBLISHED->value,
        'media'       => [],
    ]), $bundle);

    $assertSearchCachesCleared();
});
