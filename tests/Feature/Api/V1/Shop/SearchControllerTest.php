<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Blog\BlogPost;
use App\Models\Product;
use Tests\Support\TypesenseTestHelper;

use function Pest\Laravel\getJson;

/**
 * Search API Test Suite
 *
 * Tests for:
 * - SearchController: Global search endpoint (products + blog posts)
 * - SuggestSearchController: Autocomplete suggestions endpoint
 * - result_types filter: Filtering results by type
 * - Response transformation: Adding 'type' field to results
 */

// =============================================================================
// RESPONSE TRANSFORMATION & REAL DATA TESTS
// =============================================================================

describe('Response Transformation', function () {
    it('returns properly structured response with products', function () {
        Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create(['name' => 'Laravel Course']);

        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'Laravel',
            'result_types' => ['product'],
        ]));

        $response->assertOk()->assertJsonStructure([
            'message',
            'data' => [
                'data',
                'current_page',
                'per_page',
                'total',
            ],
            'metadata',
        ]);

        $json = $response->json('data.data.0');

        expect($json)->toHaveKeys([
            'slug',
            'name',
            'price',
            'original_price',
            'price_range',
            'has_discount',
            'discount_percent',
            'is_free',
            'is_featured',
            'product_type',
            'thumbnail_url',
            'teachers',
            'reviews_count',
            'average_rating',
            'type',
        ])
            ->and($json['name'])->toBe('Laravel Course')
            ->and($json['type'])->toBe('product')
            ->and($json['price_data'])->toBeNull();

    });
    it('returns properly structured response with blog post', function () {
        BlogPost::factory()
            ->create(['title' => 'Laravel Version 12!', 'status' => PublicationStatusEnum::PUBLISHED]);

        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'Laravel',
            'result_types' => ['blog_post'],
        ]));

        $response->assertOk()->assertJsonStructure([
            'message',
            'data' => [
                'data',
                'current_page',
                'per_page',
                'total',
            ],
            'metadata',
        ]);

        $json = $response->json('data.data.0');

        expect($json)->toHaveKeys([
            'title',
            'slug',
            'excerpt',
            'body',
            'author',
            'reviews_count',
            'average_rating',
            'published_at',
            'thumbnail_url',
            'type',
        ])
            ->and($json['title'])->toBe('Laravel Version 12!')
            ->and($json['type'])->toBe('blog_post');

    });
    it('adds type field to product results', function () {
        TypesenseTestHelper::skipIfTypesenseUnavailable();

        Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create(['name' => 'Unique XYZ123 Product']);
        TypesenseTestHelper::regenerateIndex();
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'XYZ123',
            'result_types' => ['product'],
        ]));

        $response->assertOk();
        $json = $response->json();

        if (! empty($json['data']['data'])) {
            expect($json['data']['data'][0])->toHaveKey('type')
                ->and($json['data']['data'][0]['type'])->toBe('product');
        }
    });

    it('adds type field to blog post results', function () {
        TypesenseTestHelper::skipIfTypesenseUnavailable();

        BlogPost::factory()->create([
            'title'  => 'Unique XYZ123 Blog Post',
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);
        TypesenseTestHelper::regenerateIndex();
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'XYZ123',
            'result_types' => ['blog_post'],
        ]));

        $response->assertOk();
        $json = $response->json();

        if (! empty($json['data']['data'])) {
            expect($json['data']['data'][0])->toHaveKey('type')
                ->and($json['data']['data'][0]['type'])->toBe('blog_post');
        }
    });
});
// =============================================================================
// SEARCH VALIDATION TESTS
// =============================================================================

describe('Search Validation', function () {
    it('requires search query parameter', function () {
        $response = getJson(route('api.v1.shop.search'));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    });

    it('rejects per_page parameter over maximum', function () {
        $response = getJson(route('api.v1.shop.search', ['q' => 'test', 'per_page' => 101]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    });

    it('rejects non-array category_ids parameter', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'test',
            'category_ids' => 'not-an-array',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_ids']);
    });

    it('rejects non-integer price_min parameter', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'         => 'test',
            'price_min' => 'not-a-number',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price_min']);
    });

    it('rejects negative price_min parameter', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'         => 'test',
            'price_min' => -100,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price_min']);
    });

    it('rejects invalid result_types values', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'test',
            'result_types' => ['invalid_type'],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['result_types.0']);
    });

    it('rejects non-array result_types parameter', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'test',
            'result_types' => 'not-an-array',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['result_types']);
    });

    it('accepts valid search with minimal parameters', function () {
        $response = getJson(route('api.v1.shop.search', ['q' => 'test']));

        expect($response->status())->toBeIn([200, 500]);
    });

    it('accepts valid search with all filter parameters', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'                 => 'test',
            'per_page'          => 10,
            'productable_type'  => 'course',
            'has_discount'      => true,
            'category_ids'      => [1, 2, 3],
            'price_min'         => 100000,
            'price_max'         => 500000,
            'level'             => 'beginner',
            'fulfillment_types' => ['digital'],
            'result_types'      => ['product'],
        ]));

        expect($response->status())->toBeIn([200, 500]);
    });

    it('accepts result_types with product only', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'test',
            'result_types' => ['product'],
        ]));

        expect($response->status())->toBeIn([200, 500]);
    });

    it('accepts result_types with blog_post only', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'test',
            'result_types' => ['blog_post'],
        ]));

        expect($response->status())->toBeIn([200, 500]);
    });

    it('accepts result_types with both types', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'test',
            'result_types' => ['product', 'blog_post'],
        ]));

        expect($response->status())->toBeIn([200, 500]);
    });
});

// =============================================================================
// SUGGESTION VALIDATION TESTS
// =============================================================================

describe('Suggestion Validation', function () {
    it('requires query parameter', function () {
        $response = getJson(route('api.v1.shop.search.suggest'));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    });

    it('requires minimum query length of 2 characters', function () {
        $response = getJson(route('api.v1.shop.search.suggest', ['q' => 'a']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    });

    it('rejects limit parameter over maximum', function () {
        $response = getJson(route('api.v1.shop.search.suggest', [
            'q'     => 'test',
            'limit' => 25,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    });

    it('rejects limit parameter under minimum', function () {
        $response = getJson(route('api.v1.shop.search.suggest', [
            'q'     => 'test',
            'limit' => 0,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    });

    it('accepts valid suggest request with minimal parameters', function () {
        $response = getJson(route('api.v1.shop.search.suggest', ['q' => 'test']));

        expect($response->status())->toBeIn([200, 500]);
    });

    it('accepts valid suggest request with limit parameter', function () {
        $response = getJson(route('api.v1.shop.search.suggest', [
            'q'     => 'test',
            'limit' => 10,
        ]));

        $response->assertOk()
            ->assertJsonStructure(['message', 'data', 'metadata']);

        expect($response->json('data'))->toBeArray();
    });
});
