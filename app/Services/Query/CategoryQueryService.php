<?php

namespace App\Services\Query;

use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductPriceService;
use App\Services\Shop\ProductQueryService;

class CategoryQueryService
{
    public function __construct(
        protected ProductPriceService $priceService,
    )
    {
    }

    public function getProductsForCategory(Category $category, ProductableEnum $type, int $limit, bool $paginate = false)
    {
        $query = ProductQueryService::make()
            ->ofType($type)
            ->inCategories([$category->id])
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
