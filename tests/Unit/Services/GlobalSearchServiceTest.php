<?php

declare(strict_types=1);
use App\Data\Shop\Search\SearchData;
use App\Services\GlobalSearchService;
use Laravel\Scout\EngineManager;

describe('Filter Building', function () {
    it('builds complete product filters with all parameters', function () {
        $service    = new GlobalSearchService(app(EngineManager::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $searchData = SearchData::from([
            'q'                 => 'test',
            'productable_type'  => 'course',
            'has_discount'      => true,
            'category_slugs'      => ["art", "science", "math"],
            'price_min'         => 100000,
            'price_max'         => 500000,
            'difficulty_level'  => 'beginner',
            'fulfillment_types' => ['digital', 'physical'],
        ]);

        $result = $method->invoke($service, $searchData);

        expect($result)->toContain('status:=published')
            ->and($result)->toContain('is_visible:=true')
            ->and($result)->toContain('productable_type:=course')
            ->and($result)->toContain('has_discount:=true')
            ->and($result)->toContain('category_slugs:=[art,science,math]')
            ->and($result)->toContain('price:[100000..500000]')
            ->and($result)->toContain('difficulty_level:=beginner')
            ->and($result)->toContain('fulfillment_types:=digital')
            ->and($result)->toContain('fulfillment_types:=physical');
    });

    it('builds filters with has_discount=false', function () {
        $service    = new GlobalSearchService(app(EngineManager::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test', 'has_discount' => false]));

        expect($result)->toContain('has_discount:=false');
    });

    it('builds filters with price_min only', function () {
        $service    = new GlobalSearchService(app(EngineManager::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test', 'price_min' => 100000]));

        expect($result)->toContain('price:>=100000');
    });

    it('builds filters with price_max only', function () {
        $service    = new GlobalSearchService(app(EngineManager::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test', 'price_max' => 500000]));

        expect($result)->toContain('price:<=500000');
    });

    it('excludes fulfillment_types when empty', function () {
        $service    = new GlobalSearchService(app(EngineManager::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test', 'fulfillment_types' => []]));

        expect($result)->not->toContain('fulfillment_types');
    });

    it('builds blog filters correctly', function () {
        $service    = new GlobalSearchService(app(EngineManager::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildBlogFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test']));

        expect($result)->toBe('status:=published');
    });
});
