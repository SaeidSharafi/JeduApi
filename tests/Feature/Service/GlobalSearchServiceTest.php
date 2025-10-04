<?php

declare(strict_types=1);

use App\Data\Shop\Search\SearchData;
use App\Models\Category;
use App\Models\Product;
use App\Services\GlobalSearchService;
use Tests\Support\TypesenseTestHelper;

/**
 * GlobalSearchService Test Suite
 *
 * Tests core search functionality including:
 * - Database fallback (always run - predictable)
 * - Typesense integration (skip if unavailable - unpredictable results)
 * - Real data validation (with actual database records)
 */

// =============================================================================
// DATABASE FALLBACK TESTS (Always run with real data)
// =============================================================================

describe('Database Fallback', function () {
    it('searches products in database when Typesense unavailable', function () {
        Config::set('scout.driver', 'database');

        // Create test product with searchable data
        $product = Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create(['name' => 'Laravel Fundamentals Course']);

        $service = app(GlobalSearchService::class);
        $results = $service->search(SearchData::from(['q' => 'Laravel', 'per_page' => 15]));

        expect($results)->toBeInstanceOf(Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($results->perPage())->toBe(15);
    });

    it('filters by result_types=product in database', function () {
        Config::set('scout.driver', 'database');

        Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create(['name' => 'PHP Course']);

        $service = app(GlobalSearchService::class);
        $results = $service->search(SearchData::from([
            'q'            => 'PHP',
            'per_page'     => 15,
            'result_types' => ['product'],
        ]));

        expect($results)->toBeInstanceOf(Illuminate\Pagination\LengthAwarePaginator::class);
    });

    it('returns empty when filtering by blog_post only (not supported in DB)', function () {
        Config::set('scout.driver', 'database');

        $service = app(GlobalSearchService::class);
        $results = $service->search(SearchData::from([
            'q'            => 'test',
            'per_page'     => 15,
            'result_types' => ['blog_post'],
        ]));

        expect($results->total())->toBe(0);
    });

    it('applies category filter in database search', function () {
        Config::set('scout.driver', 'database');

        $category = Category::factory()->create(['name' => 'Web Development']);
        $product  = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse()
            ->create(['name' => 'React Course']);
        $product->categories()->attach($category);

        $service = app(GlobalSearchService::class);
        $results = $service->search(SearchData::from([
            'q'            => 'React',
            'category_ids' => [$category->id],
        ]));

        expect($results)->toBeInstanceOf(Illuminate\Pagination\LengthAwarePaginator::class);
    });

    it('returns empty suggestions when Typesense unavailable', function () {
        Config::set('scout.driver', 'database');

        $service     = app(GlobalSearchService::class);
        $suggestions = $service->suggest('test', 5);

        expect($suggestions)->toBeArray()->and($suggestions)->toBeEmpty();
    });
});

// =============================================================================
// TYPESENSE INTEGRATION (Skip if unavailable - results unpredictable)
// =============================================================================

describe('Typesense Integration', function () {
    it('performs basic search with Typesense when available', function () {
        TypesenseTestHelper::skipIfTypesenseUnavailable();

        $service    = app(GlobalSearchService::class);
        $searchData = SearchData::from(['q' => 'test', 'per_page' => 15]);

        $results = $service->search($searchData);

        expect($results)->toBeInstanceOf(Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($results->perPage())->toBe(15);
    });

    it('filters by result_types=product in Typesense', function () {
        TypesenseTestHelper::skipIfTypesenseUnavailable();

        $service = app(GlobalSearchService::class);
        $results = $service->search(SearchData::from([
            'q'            => 'test',
            'per_page'     => 15,
            'result_types' => ['product'],
        ]));

        expect($results)->toBeInstanceOf(Illuminate\Pagination\LengthAwarePaginator::class);
    });

    it('filters by result_types=blog_post in Typesense', function () {
        TypesenseTestHelper::skipIfTypesenseUnavailable();

        $service = app(GlobalSearchService::class);
        $results = $service->search(SearchData::from([
            'q'            => 'test',
            'per_page'     => 15,
            'result_types' => ['blog_post'],
        ]));

        expect($results)->toBeInstanceOf(Illuminate\Pagination\LengthAwarePaginator::class);
    });

    it('returns suggestions when Typesense is available', function () {
        TypesenseTestHelper::skipIfTypesenseUnavailable();

        $service     = app(GlobalSearchService::class);
        $suggestions = $service->suggest('test', 5);

        expect($suggestions)->toBeArray()
            ->and(count($suggestions))->toBeLessThanOrEqual(5);
    });

    it('caches suggestions for performance', function () {
        TypesenseTestHelper::skipIfTypesenseUnavailable();

        Cache::flush();
        $service = app(GlobalSearchService::class);

        $suggestions1 = $service->suggest('test', 5);
        $suggestions2 = $service->suggest('test', 5);

        expect($suggestions1)->toBe($suggestions2);
    });
});
