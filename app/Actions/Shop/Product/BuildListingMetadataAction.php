<?php

declare(strict_types=1);

namespace App\Actions\Shop\Product;

use App\Enums\Product\ProductableEnum;
use App\Models\Category;
use App\Services\CataloguePriceBoundService;

/**
 * Builds the envelope metadata of a catalogue listing response.
 *
 * A listing describes its scope's Price Bound — the catalogue constant of the type
 * listing, or of the requested category for that type. The shape is the same one
 * global search publishes, so the envelope metadata of every priced listing has a
 * single author.
 */
final readonly class BuildListingMetadataAction
{
    public function __construct(private CataloguePriceBoundService $priceBoundService) {}

    /**
     * The metadata of one productable type's listing.
     *
     * @return array<string, mixed>
     */
    public function forListing(ProductableEnum $productableType): array
    {
        return $this->envelope($this->priceBoundService->forCatalogue($productableType));
    }

    /**
     * The metadata of a listing of one productable type within a category.
     *
     * @return array<string, mixed>
     */
    public function forCategory(Category $category, ProductableEnum $productableType): array
    {
        return $this->envelope($this->priceBoundService->forCategoryCatalogue($category, $productableType));
    }

    /**
     * @param  array{min: int|null, max: int|null}  $priceBound
     * @return array<string, mixed>
     */
    private function envelope(array $priceBound): array
    {
        return [
            'filters' => [
                'price' => $priceBound,
            ],
        ];
    }
}
