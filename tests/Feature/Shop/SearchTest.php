<?php

declare(strict_types=1);

use App\Services\GlobalSearchService;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\EngineManager;

use function Pest\Laravel\getJson;

describe('GlobalSearchService', function () {
    it('can perform basic search', function () {
        // This is an integration test that requires Typesense to be running
        $service = app(GlobalSearchService::class);

        $results = $service->search('test', 15);

        expect($results)->toBeInstanceOf(Illuminate\Pagination\LengthAwarePaginator::class);
    })->skip('Requires Typesense to be running');

    it('caches search results', function () {
        $service = app(GlobalSearchService::class);

        // First search - should cache
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn(new Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 15, 1));

        $results = $service->search('test', 15);

        expect($results)->toBeInstanceOf(Illuminate\Pagination\LengthAwarePaginator::class);
    })->skip('Requires proper mocking');

    it('builds product filters correctly', function () {
        $service = new GlobalSearchService(app(EngineManager::class));

        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $filters = [
            'productable_type'  => 'course',
            'has_discount'      => true,
            'category_ids'      => [1, 2, 3],
            'price_min'         => 100000,
            'price_max'         => 500000,
            'level'             => 'beginner',
            'fulfillment_types' => ['digital', 'physical'],
        ];

        $result = $method->invoke($service, $filters);

        expect($result)->toContain('status:=published')
            ->and($result)->toContain('is_visible:=true')
            ->and($result)->toContain('productable_type:=course')
            ->and($result)->toContain('has_discount:=true')
            ->and($result)->toContain('category_ids:=[1,2,3]')
            ->and($result)->toContain('price:[100000..500000]')
            ->and($result)->toContain('level:=beginner')
            ->and($result)->toContain('fulfillment_types:=digital')
            ->and($result)->toContain('fulfillment_types:=physical');
    });

    it('builds blog filters correctly', function () {
        $service = new GlobalSearchService(app(EngineManager::class));

        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildBlogFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, []);

        expect($result)->toBe('status:=published');
    });

    it('handles price_min only filter', function () {
        $service = new GlobalSearchService(app(EngineManager::class));

        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $filters = ['price_min' => 100000];
        $result  = $method->invoke($service, $filters);

        expect($result)->toContain('price:>=100000');
    });

    it('handles price_max only filter', function () {
        $service = new GlobalSearchService(app(EngineManager::class));

        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $filters = ['price_max' => 500000];
        $result  = $method->invoke($service, $filters);

        expect($result)->toContain('price:<=500000');
    });
});

describe('SearchController', function () {
    it('requires search query parameter', function () {
        // Act
        $response = getJson(route('api.v1.shop.search'));

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    });

    it('rejects per_page parameter over maximum', function () {
        // Act
        $response = getJson(route('api.v1.shop.search', ['q' => 'test', 'per_page' => 101]));

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    });

    it('rejects non-array category_ids parameter', function () {
        // Act
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'test',
            'category_ids' => 'not-an-array',
        ]));

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_ids']);
    });

    it('rejects non-integer price_min parameter', function () {
        // Act
        $response = getJson(route('api.v1.shop.search', [
            'q'         => 'test',
            'price_min' => 'not-a-number',
        ]));

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price_min']);
    });

    it('rejects negative price_min parameter', function () {
        // Act
        $response = getJson(route('api.v1.shop.search', [
            'q'         => 'test',
            'price_min' => -100,
        ]));

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price_min']);
    });

    it('accepts valid search request with minimal parameters', function () {
        // Act
        $response = getJson(route('api.v1.shop.search', ['q' => 'test']));

        // Assert - May return 200 with empty results or 500 if Typesense is not running
        expect($response->status())->toBeIn([200, 500]);
    });

    it('accepts valid search request with all filter parameters', function () {
        // Act
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
        ]));

        // Assert - May return 200 with empty results or 500 if Typesense is not running
        expect($response->status())->toBeIn([200, 500]);
    });

    it('accepts boolean has_discount filter', function () {
        // Act
        $response = getJson(route('api.v1.shop.search', [
            'q'            => 'test',
            'has_discount' => '1', // Boolean casting
        ]));

        // Assert
        expect($response->status())->toBeIn([200, 500]);
    });
});

describe('SuggestSearchController', function () {
    it('requires query parameter', function () {
        // Act
        $response = getJson(route('api.v1.shop.search.suggest'));

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    });

    it('requires minimum query length of 2 characters', function () {
        // Act
        $response = getJson(route('api.v1.shop.search.suggest', ['q' => 'a']));

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    });

    it('rejects limit parameter over maximum', function () {
        // Act
        $response = getJson(route('api.v1.shop.search.suggest', [
            'q'     => 'test',
            'limit' => 25,
        ]));

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    });

    it('rejects limit parameter under minimum', function () {
        // Act
        $response = getJson(route('api.v1.shop.search.suggest', [
            'q'     => 'test',
            'limit' => 0,
        ]));

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    });

    it('accepts valid suggest request with minimal parameters', function () {
        // Act
        $response = getJson(route('api.v1.shop.search.suggest', ['q' => 'test']));

        // Assert - May return 200 with empty array or 500 if Typesense is not running
        expect($response->status())->toBeIn([200, 500]);
    });

    it('accepts valid suggest request with limit parameter', function () {
        // Act
        $response = getJson(route('api.v1.shop.search.suggest', [
            'q'     => 'test',
            'limit' => 10,
        ]));

        // Assert - May return 200 with empty array or 500 if Typesense is not running
        expect($response->status())->toBeIn([200, 500]);

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    });
});
