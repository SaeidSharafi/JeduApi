<?php

declare(strict_types=1);

namespace App\Services\LearningPath;

use App\Models\LearningPath;
use App\Models\LearningPathStep;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\ProductPriceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class LearningPathStepResolverService
{
    public function __construct(private ProductPriceService $priceService) {}

    /**
     * Resolves each step to its currently sellable Product (if any), availability and price.
     * Expects `steps.productable` to be loaded.
     *
     * @return Collection<int, ResolvedLearningPathStep>
     */
    public function resolve(LearningPath $learningPath): Collection
    {
        $steps = $learningPath->steps;

        if ($steps->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->publishedAndVisible()
            ->hasPublishedDeliveryOption()
            ->publishedProductable()
            ->activeTerm()
            ->forListing()
            ->forProductables($steps->map(static fn (LearningPathStep $step): array => [
                'type' => (string) $step->productable_type,
                'id'   => (int) $step->productable_id,
            ]))
            ->withExists([
                'productDeliveryOptions as is_available' => static fn (Builder $query): Builder => self::scopeAvailableWithCapacity($query),
            ])
            ->get()
            ->keyBy(static fn (Product $product): string => self::key(
                (string) $product->productable_type,
                (int) $product->productable_id,
            ));

        $prices = $products->mapWithKeys(fn (Product $product): array => [
            $product->id => $this->priceService->getPriceDataForProduct($product),
        ]);

        return $steps->map(static function (LearningPathStep $step) use ($products, $prices): ResolvedLearningPathStep {
            /** @var Product|null $product */
            $product = $products->get(self::key((string) $step->productable_type, (int) $step->productable_id));

            return new ResolvedLearningPathStep(
                step: $step,
                product: $product,
                priceData: $product ? $prices->get($product->id) : null,
                available: (bool) $product?->getAttribute('is_available'),
            );
        })->values();
    }

    private static function key(string $type, int $id): string
    {
        return $type.':'.$id;
    }

    /**
     * @param  Builder<ProductDeliveryOption>  $query
     * @return Builder<ProductDeliveryOption>
     */
    private static function scopeAvailableWithCapacity(Builder $query): Builder
    {
        return $query->availableWithCapacity();
    }
}
