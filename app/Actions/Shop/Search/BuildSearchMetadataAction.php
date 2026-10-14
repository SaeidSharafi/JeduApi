<?php

declare(strict_types=1);

namespace App\Actions\Shop\Search;

use App\Data\Shop\Search\SearchData;
use App\Enums\Product\ProductableEnum;
use App\Query\ProductListingScope;
use App\Services\CataloguePriceBoundService;

/**
 * Builds the envelope metadata of a global search response.
 *
 * A search describes product prices only when it can return products, and only for
 * the catalogue it can return them from: the requested productable type, or every
 * shopped type when the request names none (bundles are never part of that
 * catalogue). The bound is the catalogue constant of that scope — the request's own
 * filters and query string never move it.
 */
final readonly class BuildSearchMetadataAction
{
    /**
     * The productable types of the shopped catalogue. A bundle is not one of them:
     * `/bundles` carries no bound and bundles are never indexed for search.
     */
    private const array SHOPPED_TYPES = [
        ProductableEnum::COURSE->value,
        ProductableEnum::SEMINAR->value,
        ProductableEnum::DIGITAL_ASSET->value,
    ];

    /**
     * Cache scope of a search spanning every shopped productable type.
     */
    private const string SEARCH_SCOPE = 'search';

    public function __construct(private CataloguePriceBoundService $priceBoundService) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(SearchData $searchData): array
    {
        $productableType = ProductableEnum::tryFrom((string) $searchData->productable_type);

        if (! $searchData->returnsProducts() || ! $this->isShoppedType($productableType)) {
            return [];
        }

        return [
            'filters' => [
                'price' => $this->priceBoundService->forScope(
                    $this->scopeKey($productableType),
                    ProductListingScope::forListing($productableType),
                ),
            ],
        ];
    }

    /**
     * Whether the requested type belongs to the shopped catalogue. No type at all
     * means every shopped type.
     */
    private function isShoppedType(?ProductableEnum $productableType): bool
    {
        return $productableType === null
            || in_array($productableType->value, self::SHOPPED_TYPES, true);
    }

    /**
     * The cache scope key of the searched catalogue.
     */
    private function scopeKey(?ProductableEnum $productableType): string
    {
        return $productableType === null
            ? self::SEARCH_SCOPE
            : self::SEARCH_SCOPE.':'.$productableType->value;
    }
}
