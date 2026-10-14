<?php

declare(strict_types=1);
use App\Contracts\Cache\CacheStore;
use App\Data\Shop\Search\SearchData;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Services\GlobalSearchService;
use Carbon\Carbon;

describe('Filter Building', function (): void {
    it('builds complete product filters with all parameters', function (): void {
        $service    = new GlobalSearchService(app(CacheStore::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $searchData = SearchData::from([
            'q'                => 'test',
            'productable_type' => 'course',
            'filter'           => [
                'with_discounts'    => true,
                'category_slugs'    => ['art', 'science', 'math'],
                'min_price'         => 100000,
                'max_price'         => 500000,
                'difficulty_level'  => 'beginner',
                'fulfillment_types' => ['digital', 'physical'],
            ],
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

    it('builds filters with has_discount=false', function (): void {
        $service    = new GlobalSearchService(app(CacheStore::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test', 'filter' => ['with_discounts' => false]]));

        expect($result)->toContain('has_discount:=false');
    });

    it('builds filters with price_min only', function (): void {
        $service    = new GlobalSearchService(app(CacheStore::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test', 'filter' => ['min_price' => 100000]]));

        expect($result)->toContain('price:>=100000');
    });

    it('builds filters with price_max only', function (): void {
        $service    = new GlobalSearchService(app(CacheStore::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test', 'filter' => ['max_price' => 500000]]));

        expect($result)->toContain('price:<=500000');
    });

    it('excludes fulfillment_types when empty', function (): void {
        $service    = new GlobalSearchService(app(CacheStore::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test', 'fulfillment_types' => []]));

        expect($result)->not->toContain('fulfillment_types');
    });

    it('applies the default available-now window when the search carries no availability filter', function (): void {
        $this->travelTo(Carbon::parse('2025-03-15 12:00:00', 'UTC'));

        $service    = new GlobalSearchService(app(CacheStore::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test']));

        expect($result)->toContain('earliest_availability_start_ts:<=')
            ->and($result)->toContain('latest_availability_end_ts:>=')
            ->and($result)->toContain('latest_event_ended_ts:>=');
    });

    it('applies the availability status and capacity filters to the search scope', function (): void {
        $this->travelTo(Carbon::parse('2025-03-15 12:00:00', 'UTC'));

        $service    = new GlobalSearchService(app(CacheStore::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildProductFilters');
        $method->setAccessible(true);

        $searchData = SearchData::from([
            'q'                => 'test',
            'productable_type' => 'course',
            'filter'           => [
                'availability_status' => AvailabilityStatusEnum::PAST->value,
                'near_capacity_only'  => true,
                'capacity_threshold'  => 0.75,
            ],
        ]);

        $result = $method->invoke($service, $searchData);

        expect($result)->toContain('latest_event_ended_ts:<')
            ->and($result)->toContain('max_capacity_utilization:>=0.75')
            ->and($result)->not->toContain('earliest_availability_start_ts');
    });

    it('builds blog filters correctly', function (): void {
        $service    = new GlobalSearchService(app(CacheStore::class));
        $reflection = new ReflectionClass($service);
        $method     = $reflection->getMethod('buildBlogFilters');
        $method->setAccessible(true);

        $result = $method->invoke($service, SearchData::from(['q' => 'test']));

        expect($result)->toBe('status:=published');
    });
});
