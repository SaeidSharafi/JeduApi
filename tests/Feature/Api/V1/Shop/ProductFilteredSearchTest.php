<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\Product;
use App\Services\GlobalSearchService;

use function Pest\Laravel\getJson;

/**
 * A search carrying a product filter can never be satisfied by an article:
 * every filter in the product filter set selects on a product's own attributes.
 * Blog posts are therefore excluded from the result set, even when the client
 * explicitly asks for them.
 */
covers(GlobalSearchService::class);

beforeEach(function (): void {
    Config::set('scout.driver', 'database');
});

describe('Product-filtered search', function (): void {
    it('excludes blog posts when the client explicitly asks for them', function (array $filter): void {
        BlogPost::factory()->create([
            'title'  => 'Excluded Article',
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'Excluded',
            'result_types' => ['blog_post'],
            'filter'       => $filter,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonCount(0, 'data.data');
    })->with([
        'category filter'      => [['category_slugs' => ['programming']]],
        'fulfillment filter'   => [['fulfillment_types' => ['digital']]],
        'difficulty filter'    => [['difficulty_level' => 'beginner']],
        'price filter'         => [['min_price' => 100_000]],
        'discount filter'      => [['with_discounts' => true]],
        'availability filter'  => [['availability_status' => 'past']],
        'capacity filter'      => [['near_capacity_only' => true]],
        'capacity threshold'   => [['capacity_threshold' => 0.5]],
        'available now filter' => [['is_available_now' => true]],
        'every filter field'   => [[
            'category_slugs'            => ['programming'],
            'fulfillment_types'         => ['digital'],
            'difficulty_level'          => 'beginner',
            'min_price'                 => 100_000,
            'max_price'                 => 500_000,
            'with_discounts'            => true,
            'is_available_now'          => true,
            'near_capacity_only'        => true,
            'capacity_threshold'        => 0.5,
            'availability_status'       => 'past',
            'registration_starts_after' => '1404-01-01',
            'registration_ends_before'  => '1404-02-01',
            'available_from'            => '1404-01-01',
            'available_to'              => '1404-02-01',
        ]],
    ]);

    it('returns blog posts when no product filter is present', function (): void {
        BlogPost::factory()->create([
            'title'  => 'Kept Article',
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'Kept',
            'result_types' => ['blog_post'],
        ]));

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.type', 'blog_post');
    });

    it('returns products and no blog posts when both types are in scope', function (): void {
        $category = Category::factory()->create();

        $product = Product::factory()
            ->withDeliveryOptions(1)
            ->withCourse()
            ->create(['name' => 'Filtered Product']);
        $product->categories()->attach($category->id);

        BlogPost::factory()->create([
            'title'  => 'Filtered Article',
            'status' => PublicationStatusEnum::PUBLISHED,
        ]);

        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'Filtered',
            'result_types' => ['product', 'blog_post'],
            'filter'       => ['category_slugs' => [$category->slug]],
        ]));

        $response->assertOk()
            ->assertJsonPath('data.total', 1);

        $types = array_column($response->json('data.data'), 'type');

        expect($types)->toBe(['product']);
    });
});
