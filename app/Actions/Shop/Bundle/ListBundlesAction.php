<?php

declare(strict_types=1);

namespace App\Actions\Shop\Bundle;

use App\Data\Shop\Product\ProductCardData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Product;
use App\Services\BundleAvailabilityService;
use App\Services\ProductPriceService;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListBundlesAction
{
    public function __construct(
        private BundleAvailabilityService $availability,
        private ProductPriceService $prices,
    ) {}

    public function handle(?int $page = null, ?int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max($perPage ?? 15, 1), 100);
        $page    = max($page ?? 1, 1);

        $products = Product::query()
            ->ofType(ProductableEnum::BUNDLE)
            ->publishedAndVisible()
            ->publishedProductable()
            ->activeTerm()
            ->with([
                'productable',
                'categories',
                'productDeliveryOptions' => fn ($query) => $query
                    ->where('status', PublicationStatusEnum::PUBLISHED)
                    ->with('teachers'),
            ])
            ->whereHas('productDeliveryOptions', fn ($query) => $query->where('status', PublicationStatusEnum::PUBLISHED))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Product $product): bool => $product->productDeliveryOptions->contains(
                fn ($option): bool => $this->availability->isAvailable($option)
            ))
            ->values();

        $items = $products->forPage($page, $perPage)->map(
            fn (Product $product): ProductCardData => ProductCardData::fromModel(
                $product,
                $this->prices->getPriceDataForProduct($product),
            )
        )->values();

        return new LengthAwarePaginator($items, $products->count(), $perPage, $page, [
            'path'  => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
