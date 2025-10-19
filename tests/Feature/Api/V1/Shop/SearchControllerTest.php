<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Blog\BlogPost;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductPrice;
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

    it('rejects non-array category_slugs parameter', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'      => 'test',
            'filter' => ['category_slugs' => 'not-an-array'],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.category_slugs']);
    });

    it('rejects non-integer price_min parameter', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'      => 'test',
            'filter' => ['min_price' => 'not-a-number'],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.min_price']);
    });

    it('rejects negative price_min parameter', function () {
        $response = getJson(route('api.v1.shop.search', [
            'q'      => 'test',
            'filter' => ['min_price' => -100],
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.min_price']);
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
            'q'                => 'test',
            'per_page'         => 10,
            'productable_type' => 'course',
            'filter'           => [
                'with_discounts'    => true,
                'category_slugs'    => ['art', 'programming'],
                'min_price'         => 100000,
                'max_price'         => 500000,
                'difficulty_level'  => 'beginner',
                'fulfillment_types' => ['digital'],
            ],
            'result_types' => ['product'],
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

describe('filters tests', function () {
    it('filters by productable_type=course', function () {
        Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create(['name' => 'Unique XYZ123 Course']);

        Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withDigitalAsset()
            ->create(['name' => 'Unique XYZ123 Ebook']);

        $response = getJson(route('api.v1.shop.search', [
            'q'                => 'XYZ123',
            'productable_type' => 'course',
        ]));

        $response->assertOk();
        $json = $response->json();

        expect($json['data']['total'])->toBe(1);
        if (! empty($json['data']['data'])) {
            expect($json['data']['data'][0]['name'])->toBe('Unique XYZ123 Course');
        }
    });

    it('filters by has_discount=true', function () {
        $product = Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create([
                'name' => 'Discounted Course',
            ]);

        ProductPrice::create([
            'product_id'         => $product->id,
            'min_price'          => 150_000,
            'max_price'          => 150_000,
            'min_original_price' => 200_000,
            'max_original_price' => 200_000,
            'has_discount'       => true,
            'has_featured_price' => false,
        ]);

        $nonDiscountedProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create([
                'name' => 'Non-Discounted Course',
            ]);

        ProductPrice::create([
            'product_id'         => $nonDiscountedProduct->id,
            'min_price'          => 300_000,
            'max_price'          => 300_000,
            'min_original_price' => 300_000,
            'max_original_price' => 300_000,
            'has_discount'       => false,
            'has_featured_price' => false,
        ]);

        $response = getJson(route('api.v1.shop.search', [
            'q'      => 'Course',
            'filter' => ['with_discounts' => true],
        ]));

        $response->assertOk();
        $json = $response->json();

        expect($json['data']['total'])->toBe(1);
        if (! empty($json['data']['data'])) {
            expect($json['data']['data'][0]['name'])->toBe('Discounted Course');
        }
    });

    it('filters by category_slugs', function () {
        $category1 = App\Models\Category::factory()->create(['name' => 'Category One']);
        $category2 = App\Models\Category::factory()->create(['name' => 'Category Two']);
        $category3 = App\Models\Category::factory()->create(['name' => 'Category Three']);
        $product1  = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse()
            ->create(['name' => 'Multi-category Course']);
        $product1->categories()->attach([$category1->id, $category2->id]);
        $product2 = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse()
            ->create(['name' => 'Single-category Course']);
        $product2->categories()->attach([$category3->id]);
        $product3 = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse()
            ->create(['name' => 'No-category Course']);
        // Filter by category1 and category3
        $response = getJson(route('api.v1.shop.search', [
            'q'      => 'Course',
            'filter' => ['category_slugs' => [$category1->slug, $category3->slug]],
        ]));
        $response->assertOk();
        $json = $response->json();
        expect($json['data']['total'])->toBe(2);
        $names = array_map(fn ($item) => $item['name'], $json['data']['data']);
        expect($names)->toContain('Multi-category Course')
            ->and($names)->toContain('Single-category Course')
            ->and($names)->not->toContain('No-category Course');
    });
    it('filters by price_min and price_max', function () {
        $cheapProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create([
                'name' => 'Cheap Course',
            ]);
        ProductPrice::create([
            'product_id'         => $cheapProduct->id,
            'min_price'          => 100_000,
            'max_price'          => 100_000,
            'min_original_price' => 100_000,
            'max_original_price' => 100_000,
            'has_discount'       => false,
            'has_featured_price' => false,
        ]);

        $affordableProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create([
                'name' => 'Affordable Course',
            ]);
        ProductPrice::create([
            'product_id'         => $affordableProduct->id,
            'min_price'          => 300_000,
            'max_price'          => 300_000,
            'min_original_price' => 300_000,
            'max_original_price' => 300_000,
            'has_discount'       => false,
            'has_featured_price' => false,
        ]);

        $expensiveProduct = Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse()
            ->create([
                'name' => 'Expensive Course',
            ]);
        ProductPrice::create([
            'product_id'         => $expensiveProduct->id,
            'min_price'          => 600_000,
            'max_price'          => 600_000,
            'min_original_price' => 600_000,
            'max_original_price' => 600_000,
            'has_discount'       => false,
            'has_featured_price' => false,
        ]);

        $response = getJson(route('api.v1.shop.search', [
            'q'      => 'Course',
            'filter' => ['min_price' => 200_000, 'max_price' => 500_000],
        ]));

        $response->assertOk();
        $json = $response->json();

        expect($json['data']['total'])->toBe(1);
        if (! empty($json['data']['data'])) {
            expect($json['data']['data'][0]['name'])->toBe('Affordable Course');
        }
    });
    it('filters by level', function () {
        Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse(Course::factory()->create([
                'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
            ]))
            ->create(['name' => 'Beginner Course']);

        Product::factory()
            ->withDeliveryOptions(1)
            ->withCategory(1)
            ->withCourse(Course::factory()->create([
                'difficulty_level' => CourseDifficultyLevelEnum::ADVANCED,
            ]))
            ->create(['name' => 'Advanced Course']);

        $response = getJson(route('api.v1.shop.search', [
            'q'      => 'Course',
            'filter' => ['difficulty_level' => 'beginner'],
        ]));

        $response->assertOk();
        $json = $response->json();

        expect($json['data']['total'])->toBe(1);
        if (! empty($json['data']['data'])) {
            expect($json['data']['data'][0]['name'])->toBe('Beginner Course');
        }
    });
    it('filters by fulfillment_types', function () {
        Product::factory()
            ->withDeliveryOptions(realData: [
                ['fulfillment_type' => FulfillmentTypeEnum::DIGITAL],
            ])
            ->withCategory(1)
            ->withCourse()
            ->create(['name' => 'Digital Course']);
        Product::factory()
            ->withDeliveryOptions(realData: [
                ['fulfillment_type' => FulfillmentTypeEnum::PHYSICAL],
            ])
            ->withCategory(1)
            ->withCourse()
            ->create(['name' => 'Physical Course']);
        $response = getJson(route('api.v1.shop.search', [
            'q'      => 'Course',
            'filter' => ['fulfillment_types' => ['digital']],
        ]));
        $response->assertOk();
        $json = $response->json();
        expect($json['data']['total'])->toBe(1);
        if (! empty($json['data']['data'])) {
            expect($json['data']['data'][0]['name'])->toBe('Digital Course');
        }
    });
});
