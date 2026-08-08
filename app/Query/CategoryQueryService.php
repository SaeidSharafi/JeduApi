<?php

declare(strict_types=1);

namespace App\Query;

use App\Data\Shop\Product\ProductCardData;
use App\Enums\Product\ProductableEnum;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductPriceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class CategoryQueryService
{
    public function __construct(
        private ProductPriceService $priceService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, ProductCardData>|Collection<int, ProductCardData>
     */
    public function getProductsForCategory(Category $category, ProductableEnum $type, int $limit, bool $paginate = false): LengthAwarePaginator|Collection
    {
        $query = Product::query()
            ->ofType($type)
            ->inCategory($category->id)
            ->publishedAndVisible()
            ->hasPublishedDeliveryOption()
            ->publishedProductable()
            ->activeTerm()
            ->forListing();

        if ($paginate) {
            $products = $query->paginate($limit);
            $prices   = $this->priceService->getPriceDataForProducts($products->getCollection());

            return $products->through(
                fn (Product $product): ProductCardData => ProductCardData::fromModel($product, $prices->get($product->id))
            );
        }

        return $this->buildProductCards($query->limit($limit)->get());
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, ProductCardData>
     */
    private function buildProductCards(Collection $products): Collection
    {
        $prices = $this->priceService->getPriceDataForProducts($products);

        return $products->map(
            fn (Product $product): ProductCardData => ProductCardData::fromModel($product, $prices->get($product->id))
        );
    }
}
