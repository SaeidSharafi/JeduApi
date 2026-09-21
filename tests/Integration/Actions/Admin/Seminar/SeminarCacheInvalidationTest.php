<?php

declare(strict_types=1);

use App\Actions\Admin\Seminar\CreateSeminarAction;
use App\Actions\Admin\Seminar\DeleteSeminarAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Seminar\CreateSeminarData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\System\CacheKey;
use App\Models\Seminar;

covers(CreateSeminarAction::class, DeleteSeminarAction::class);

$warmSearchCache = function (): void {
    app(CacheStore::class)->put(CacheKey::Search, ['hash' => 'query-hash'], ['stale']);
};

$assertSearchCacheCleared = function (): void {
    expect(app(CacheStore::class)->get(CacheKey::Search, ['hash' => 'query-hash']))->toBeNull();
};

$seminarData = fn (): CreateSeminarData => CreateSeminarData::from([
    'full_name'                => 'Cache Invalidation Seminar',
    'short_name'               => 'Cache Seminar',
    'subtitle'                 => null,
    'slug'                     => 'cache-invalidation-seminar',
    'status'                   => PublicationStatusEnum::PUBLISHED->value,
    'difficulty_level'         => CourseDifficultyLevelEnum::BEGINNER->value,
    'provides_certificate'     => false,
    'description'              => 'A seminar used to prove the search cache is dropped.',
    'curriculum_summary_text'  => null,
    'outcomes_json'            => [],
    'target_audience'          => null,
    'prerequisites'            => null,
    'promo_video_external_url' => null,
    'estimated_duration_desc'  => null,
    'faq'                      => null,
    'keywords'                 => null,
    'meta_title'               => null,
    'meta_description'         => null,
    'meta_keywords'            => null,
    'categories'               => [],
    'digital_assets'           => [],
    'media'                    => [],
]);

it('clears the search cache when a seminar is created', function () use ($warmSearchCache, $assertSearchCacheCleared, $seminarData): void {
    $warmSearchCache();

    app(CreateSeminarAction::class)->handle($seminarData());

    $assertSearchCacheCleared();
});

it('clears the search cache when a seminar is deleted', function () use ($warmSearchCache, $assertSearchCacheCleared): void {
    $seminar = Seminar::factory()->create();

    $warmSearchCache();

    app(DeleteSeminarAction::class)->handle($seminar);

    $assertSearchCacheCleared();
    expect(Seminar::query()->whereKey($seminar->id)->exists())->toBeFalse();
});
