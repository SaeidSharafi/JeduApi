<?php

declare(strict_types=1);

namespace App\Actions\Shop\Bundle;

use App\Data\Shop\Product\Bundle\BundleComponentData;
use App\Data\Shop\Product\Bundle\BundleDetailData;
use App\Data\Shop\Product\Bundle\BundleOptionData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Product;
use App\Services\BundleAvailabilityService;
use App\Services\ProductPriceService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ShowBundleAction
{
    public function __construct(
        private BundleAvailabilityService $availability,
        private ProductPriceService $prices,
    ) {}

    public function handle(Product $routeProduct): BundleDetailData
    {
        $product = Product::query()
            ->ofType(ProductableEnum::BUNDLE)
            ->publishedAndVisible()
            ->publishedProductable()
            ->activeTerm()
            ->whereKey($routeProduct->id)
            ->with([
                'productable',
                'productDeliveryOptions' => fn ($query) => $query
                    ->where('status', PublicationStatusEnum::PUBLISHED)
                    ->with(['teachers', 'bundleComponents.product.productable', 'bundleComponents.teachers']),
            ])
            ->first();

        if ($product === null) {
            throw new NotFoundHttpException;
        }

        $product->productable->loadMediaWithVariantsMatchAll();

        $options = $product->productDeliveryOptions
            ->filter(fn ($option): bool => $this->availability->isAvailable($option))
            ->map(function ($option): BundleOptionData {
                $components = $option->bundleComponents->map(
                    fn ($component): BundleComponentData => BundleComponentData::fromModel(
                        $component,
                        (int) $component->pivot->allocation,
                        $this->availability->isAvailable($component),
                    )
                )->values();

                return BundleOptionData::fromModel(
                    $option,
                    $components,
                    (int) $components->sum(fn (BundleComponentData $component): int => $this->prices->getMinCurrentPrice($option->bundleComponents->firstWhere('uuid', $component->uuid)->product)
                    ),
                    true,
                    $this->availability->remainingCapacity($option),
                );
            })->values();

        if ($options->isEmpty()) {
            throw new NotFoundHttpException;
        }

        return BundleDetailData::fromModel($product, $options);
    }
}
