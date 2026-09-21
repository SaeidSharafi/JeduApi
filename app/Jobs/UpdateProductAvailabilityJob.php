<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Cache\CacheStore;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\System\CacheTag;
use App\Enums\TermStatusEnum;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Services\BundleAvailabilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class UpdateProductAvailabilityJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, int>  $productIds
     */
    public function __construct(public array $productIds) {}

    public function handle(
        CacheStore $cache,
        BundleAvailabilityService $bundleAvailabilityService,
    ): void {
        if ($this->productIds === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', $this->productIds)
            ->with([
                'productDeliveryOptions' => fn ($query) => $query->with([
                    'bundleComponents.product.productable',
                    'bundleComponents.product.term',
                ])->where('status', PublicationStatusEnum::PUBLISHED),
                'productable',
                'term:id,status',
            ])
            ->get();

        $threshold         = (float) config('products.availability.capacity_threshold', 0.8);
        $changedProductIds = [];

        foreach ($products as $product) {
            $options          = $product->productDeliveryOptions;
            $availableOptions = $options->filter(
                fn (ProductDeliveryOption $option): bool => $product->productable_type !== ProductableEnum::BUNDLE->value
                    || $bundleAvailabilityService->isAvailable($option)
            );
            $capacityOptions = $product->productable_type === ProductableEnum::BUNDLE->value
                ? $options->flatMap(fn (ProductDeliveryOption $option) => $option->bundleComponents)
                : $options;
            $ratios = $capacityOptions
                ->filter(fn ($option): bool => $option->capacity !== null && $option->capacity > 0)
                // Capacity utilization must count committed seats: enrolled (sold) plus
                // reserved (held by unpaid orders). Otherwise a sold-out option can still
                // look "not near capacity" and fail to flip its availability snapshot.
                ->map(fn ($option): float => ($option->enrolled_count + $option->reserved_count) / $option->capacity);

            $windowOptions = $product->productable_type === ProductableEnum::BUNDLE->value
                ? $options->flatMap(fn (ProductDeliveryOption $option) => $option->bundleComponents->push($option))
                : $options;
            $registrationStarts = $windowOptions->pluck('registration_start_date');
            $registrationEnds   = $windowOptions->pluck('registration_end_date');
            $availabilityStarts = $windowOptions->pluck('available_from');
            $availabilityEnds   = $windowOptions->pluck('available_to');

            $snapshot = [
                'has_published_delivery_option' => $availableOptions->isNotEmpty(),
                'productable_status'            => $product->productable?->status->value ?? PublicationStatusEnum::DRAFT->value,
                'is_term_active'                => $product->term === null || $product->term->status === TermStatusEnum::ACTIVE,
                'earliest_registration_start'   => $registrationStarts->containsStrict(null) ? null : $registrationStarts->min(),
                'latest_registration_end'       => $registrationEnds->containsStrict(null) ? null : $registrationEnds->max(),
                'earliest_availability_start'   => $availabilityStarts->containsStrict(null) ? null : $availabilityStarts->min(),
                'latest_availability_end'       => $availabilityEnds->containsStrict(null) ? null : $availabilityEnds->max(),
                'near_capacity'                 => $ratios->contains(fn (float $ratio): bool => $ratio >= $threshold),
                'max_capacity_utilization'      => $ratios->max() ?? 0,
            ];

            $product->forceFill($snapshot);

            if ($product->isDirty(array_keys($snapshot))) {
                $product->saveQuietly();
                $changedProductIds[] = $product->id;
            }
        }

        if ($changedProductIds !== []) {
            $cache->invalidate(CacheTag::Catalog, CacheTag::Search);

            ProductSearchIndexInvalidated::dispatch($changedProductIds);
        }
    }
}
