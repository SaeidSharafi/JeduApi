<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Cache\CacheStore;
use App\Data\Shop\Product\Course\ProductFilterData;
use App\Enums\Product\ProductableEnum;
use App\Enums\System\CacheKey;
use App\Models\Product;
use App\Query\ProductListingScope;

/**
 * The catalogue Price Bound of a listing scope: the lowest and highest Purchase
 * Price (`product_prices.min_price`) among the products that scope lists.
 *
 * The bound is a catalogue constant of the endpoint's scope, not of the request:
 * every request filter is ignored, so a filtered request still renders a stable
 * price slider domain. The scope also carries the listing's default availability
 * window, so an unavailable product is outside the bound exactly as it is outside
 * the listing.
 *
 * A scope with no priced product is cached as an array whose members are null.
 * The cache gateway never stores a null value, so caching null itself would be a
 * permanently missed key that regenerates on every request (ADR 0014).
 */
final readonly class CataloguePriceBoundService
{
    /**
     * The cache scope key of the whole course catalogue.
     */
    private const string COURSE_CATALOGUE_SCOPE = 'course';

    public function __construct(private CacheStore $cache) {}

    /**
     * The Price Bound of the whole course catalogue.
     *
     * The listing's default availability window applies; request filters never do.
     *
     * @return array{min: int|null, max: int|null}
     */
    public function forCourseCatalogue(): array
    {
        return $this->forScope(
            self::COURSE_CATALOGUE_SCOPE,
            ProductListingScope::forListing(
                ProductableEnum::COURSE,
                new ProductFilterData(),
            ),
        );
    }

    /**
     * The Price Bound of an arbitrary listing scope.
     *
     * The scope key is the cache identity: two scopes that share a productable type
     * but constrain the catalogue differently must pass different keys.
     *
     * @return array{min: int|null, max: int|null}
     */
    public function forScope(string $scope, ProductListingScope $listingScope): array
    {
        /** @var array{min: int|null, max: int|null} */
        return $this->cache->flexible(
            CacheKey::CataloguePriceBound,
            ['scope'     => $scope],
            fn (): array => $this->aggregate($listingScope),
        );
    }

    /**
     * @return array{min: int|null, max: int|null}
     */
    private function aggregate(ProductListingScope $listingScope): array
    {
        $bound = $listingScope
            ->applyToDatabase(Product::query())
            ->join('product_prices', 'products.id', '=', 'product_prices.product_id')
            ->selectRaw('MIN(product_prices.min_price) AS min_price, MAX(product_prices.min_price) AS max_price')
            ->toBase()
            ->first();

        return [
            'min' => $bound?->min_price === null ? null : (int) $bound->min_price,
            'max' => $bound?->max_price === null ? null : (int) $bound->max_price,
        ];
    }
}
