<?php

declare(strict_types=1);

namespace App\Query;

use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductPriceService;

final class CategoryQueryService
{
    public function __construct(
        private ProductPriceService $priceService,
    ) {}

    public function getProductsForCategory(Category $category, ProductableEnum $type, int $limit, bool $paginate = false)
    {
        $query = ProductQueryService::make()
            ->ofType($type)
            ->inCategoryIds([$category->id])
            ->availableProducts()
            ->forListing();

        $productsCollection = $paginate
            ? $query->paginate($limit)
            : $query
                ->limit($limit)
                ->get();

        $prices = $this->priceService->getPriceDataForProducts($productsCollection->collect());

        // Map the results into the ProductCardData DTO
        $productsCollection->transform(
            fn (Product $product) => ProductCardData::fromModel($product, $prices->get($product->id))
        );

        return $productsCollection;
    }
}
