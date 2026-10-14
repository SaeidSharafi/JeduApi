<?php

declare(strict_types=1);

namespace App\Query;

use App\Data\Shop\Product\Course\ProductFilterData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Scout\Builder as ScoutBuilder;

/**
 * The single definition of the storefront product listing scope.
 *
 * It owns the publish/visibility invariants, the productable type restriction, every
 * user-facing {@see ProductFilterData} clause and the default availability window, and
 * renders them either as a Typesense `filter_by` expression (literal and Scout-builder
 * forms) or as equivalent database constraints.
 */
final readonly class ProductListingScope
{
    private function __construct(
        private ?ProductableEnum $productableType,
        private ?ProductFilterData $filter,
        private bool $excludesBundles,
        private bool $appliesCatalogueInvariants,
        private bool $appliesAvailabilityWindow,
        private bool $appliesCapacityFilter,
        private bool $rendersNegativeDiscount,
        private CarbonInterface $now,
        private float $defaultCapacityThreshold,
    ) {}

    /**
     * The full listing scope shared by the Typesense and database listing paths.
     */
    public static function forListing(
        ?ProductableEnum $productableType = null,
        ?ProductFilterData $filter = null,
        ?CarbonInterface $now = null,
        ?float $capacityThreshold = null,
    ): self {
        return new self(
            productableType: $productableType,
            filter: $filter,
            excludesBundles: true,
            appliesCatalogueInvariants: true,
            appliesAvailabilityWindow: true,
            appliesCapacityFilter: true,
            rendersNegativeDiscount: false,
            now: $now                                    ?? Carbon::now(),
            defaultCapacityThreshold: $capacityThreshold ?? (float) config('products.availability.capacity_threshold', 0.8),
        );
    }

    /**
     * The category listing scope: catalogue invariants and the productable type only.
     * Category listings carry no request filters and deliberately no availability window.
     */
    public static function forCategoryListing(ProductableEnum $productableType, ?CarbonInterface $now = null): self
    {
        return new self(
            productableType: $productableType,
            filter: null,
            excludesBundles: false,
            appliesCatalogueInvariants: true,
            appliesAvailabilityWindow: false,
            appliesCapacityFilter: false,
            rendersNegativeDiscount: false,
            now: $now ?? Carbon::now(),
            defaultCapacityThreshold: (float) config('products.availability.capacity_threshold', 0.8),
        );
    }

    /**
     * The global search product mapping. It keeps global search's narrower publish scope
     * and renders an explicit `with_discounts: false`; catalogue invariants and the
     * availability window are not part of global search yet.
     */
    public static function forGlobalSearch(
        ?ProductableEnum $productableType,
        ?ProductFilterData $filter,
        ?CarbonInterface $now = null,
    ): self {
        return new self(
            productableType: $productableType,
            filter: $filter,
            excludesBundles: true,
            appliesCatalogueInvariants: false,
            appliesAvailabilityWindow: false,
            appliesCapacityFilter: false,
            rendersNegativeDiscount: true,
            now: $now ?? Carbon::now(),
            defaultCapacityThreshold: (float) config('products.availability.capacity_threshold', 0.8),
        );
    }

    /**
     * Render the scope as a Typesense `filter_by` expression.
     */
    public function toTypesenseFilter(): string
    {
        $filters = [
            'status:='.PublicationStatusEnum::PUBLISHED->value,
            'is_visible:=true',
        ];

        if ($this->excludesBundles) {
            $filters[] = 'productable_type:!='.ProductableEnum::BUNDLE->value;
        }

        if ($this->appliesCatalogueInvariants) {
            $filters[] = 'productable_status:='.PublicationStatusEnum::PUBLISHED->value;
            $filters[] = 'has_published_delivery_option:=true';
            $filters[] = 'is_term_active:=true';
        }

        if ($this->productableType !== null) {
            $filters[] = 'productable_type:='.$this->productableType->value;
        }

        array_push($filters, ...$this->typesenseFilterClauses());

        return implode(' && ', $filters);
    }

    /**
     * Apply the scope to a Scout query builder, which renders it as the same Typesense
     * `filter_by` expression at search time.
     */
    public function applyToScout(ScoutBuilder $query): ScoutBuilder
    {
        $query->where('status', PublicationStatusEnum::PUBLISHED->value)
            ->where('is_visible', true);

        if ($this->excludesBundles) {
            $query->whereNotIn('productable_type', [ProductableEnum::BUNDLE->value]);
        }

        if ($this->appliesCatalogueInvariants) {
            $query->where('productable_status', PublicationStatusEnum::PUBLISHED->value)
                ->where('has_published_delivery_option', true)
                ->where('is_term_active', true);
        }

        if ($this->productableType !== null) {
            $query->where('productable_type', $this->productableType->value);
        }

        $this->applyScoutFilterClauses($query);

        return $query;
    }

    /**
     * Apply the equivalent database constraints to a product query.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function applyToDatabase(Builder $query): Builder
    {
        if ($this->excludesBundles) {
            $query->where('products.productable_type', '!=', ProductableEnum::BUNDLE->value);
        }

        if ($this->productableType !== null) {
            $query->where('products.productable_type', $this->productableType->value);
        }

        if ($this->appliesCatalogueInvariants) {
            $query = ProductAvailabilityFilter::applyPublishedAndVisible($query);
            $query = ProductAvailabilityFilter::applyHasPublishedDeliveryOption($query);
            $query = ProductAvailabilityFilter::applyPublishedProductable($query);
            $query = ProductAvailabilityFilter::applyActiveTerm($query);
        }

        $query = $this->applyDatabaseFilterClauses($query);

        if ($this->appliesAvailabilityWindow) {
            $query = $this->applyDatabaseAvailabilityClauses($query);
        }

        return $query;
    }

    /**
     * Whether applying this scope to a database query already joins the materialised
     * product price row.
     */
    public function joinsProductPrices(): bool
    {
        $filter = $this->filter;

        return $filter !== null
            && (bool) ($filter->min_price || $filter->max_price || $filter->with_discounts);
    }

    /**
     * The capacity ratio threshold the scope filters and sorts by.
     */
    public function capacityThreshold(): float
    {
        return $this->filter?->capacity_threshold ?? $this->defaultCapacityThreshold;
    }

    /**
     * @return list<string>
     */
    private function typesenseFilterClauses(): array
    {
        $filter = $this->filter;

        if ($filter === null) {
            return $this->appliesAvailabilityWindow ? $this->typesenseAvailabilityClauses() : [];
        }

        $clauses = [];

        if ($filter->with_discounts !== null && ($filter->with_discounts || $this->rendersNegativeDiscount)) {
            $clauses[] = 'has_discount:='.($filter->with_discounts ? 'true' : 'false');
        }

        if (! empty($filter->category_slugs)) {
            $clauses[] = 'category_slugs:=['.implode(',', $filter->category_slugs).']';
        }

        if ($filter->min_price !== null && $filter->max_price !== null) {
            $clauses[] = "price:[{$filter->min_price}..{$filter->max_price}]";
        } elseif ($filter->min_price !== null) {
            $clauses[] = 'price:>='.$filter->min_price;
        } elseif ($filter->max_price !== null) {
            $clauses[] = 'price:<='.$filter->max_price;
        }

        if (! empty($filter->difficulty_level)) {
            $clauses[] = 'difficulty_level:='.$filter->difficulty_level;
        }

        if (! empty($filter->fulfillment_types)) {
            $types     = array_map(fn (string $type): string => 'fulfillment_types:='.$type, $filter->fulfillment_types);
            $clauses[] = '('.implode(' || ', $types).')';
        }

        if ($this->appliesCapacityFilter && $filter->near_capacity_only) {
            $clauses[] = 'max_capacity_utilization:>='.$this->capacityThreshold();
        }

        if ($this->appliesAvailabilityWindow) {
            array_push($clauses, ...$this->typesenseAvailabilityClauses());
        }

        return $clauses;
    }

    private function applyScoutFilterClauses(ScoutBuilder $query): void
    {
        $filter = $this->filter;

        if ($filter === null) {
            $this->applyScoutAvailabilityClauses($query);

            return;
        }

        if ($filter->category_slugs !== null) {
            $query->whereIn('category_slugs', $filter->category_slugs);
        }

        if ($filter->difficulty_level) {
            $query->where('difficulty_level', $filter->difficulty_level);
        }

        if ($filter->fulfillment_types !== null) {
            $query->whereIn('fulfillment_types', $filter->fulfillment_types);
        }

        if ($filter->min_price !== null && $filter->max_price !== null) {
            $query->where('price', ['[', $filter->min_price, '..', $filter->max_price, ']']);
        } elseif ($filter->min_price !== null) {
            $query->where('price', ['>=', $filter->min_price]);
        } elseif ($filter->max_price !== null) {
            $query->where('price', ['<=', $filter->max_price]);
        }

        if ($filter->with_discounts) {
            $query->where('has_discount', true);
        }

        if ($this->appliesCapacityFilter && $filter->near_capacity_only) {
            $query->where('max_capacity_utilization', ['>=', $this->capacityThreshold()]);
        }

        $this->applyScoutAvailabilityClauses($query);
    }

    private function applyScoutAvailabilityClauses(ScoutBuilder $query): void
    {
        if (! $this->appliesAvailabilityWindow) {
            return;
        }

        $filter = $this->filter;
        $now    = $this->now->timestamp;

        if ($filter?->is_available_now) {
            $query->where('earliest_registration_start_ts', ['<=', $now])
                ->where('latest_registration_end_ts', ['>=', $now])
                ->where('earliest_availability_start_ts', ['<=', $now])
                ->where('latest_availability_end_ts', ['>=', $now]);

            return;
        }

        if ($filter?->registration_starts_after) {
            $query->where('latest_registration_end_ts', ['>=', $filter->registration_starts_after->timestamp]);
        }

        if ($filter?->registration_ends_before) {
            $query->where('earliest_registration_start_ts', ['<=', $filter->registration_ends_before->timestamp]);
        }

        if ($filter?->availability_status) {
            $today = $this->now->copy()->startOfDay()->timestamp;

            match ($filter->availability_status) {
                AvailabilityStatusEnum::PAST->value     => $query->where('latest_event_ended_ts', ['<', $today]),
                AvailabilityStatusEnum::UPCOMING->value => $query->where('earliest_event_start_ts', ['>', $today]),
                AvailabilityStatusEnum::ONGOING->value  => $query
                    ->where('earliest_event_start_ts', ['<=', $today])
                    ->where('latest_event_ended_ts', ['>=', $today]),
                default => null,
            };

            return;
        }

        if ($filter?->available_from || $filter?->available_to) {
            if ($filter->available_from) {
                $query->where('latest_availability_end_ts', ['>=', $filter->available_from->timestamp]);
            }

            if ($filter->available_to) {
                $query->where('earliest_availability_start_ts', ['<=', $filter->available_to->timestamp]);
            }

            return;
        }

        $query->where('earliest_availability_start_ts', ['<=', $now])
            ->where('latest_availability_end_ts', ['>=', $now])
            ->where('latest_event_ended_ts', ['>=', $this->now->copy()->startOfDay()->timestamp]);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applyDatabaseFilterClauses(Builder $query): Builder
    {
        $filter = $this->filter;

        if ($filter === null) {
            return $query;
        }

        if ($filter->category_slugs) {
            $query->whereHas('categories', fn (Builder $categoryQuery): Builder => $categoryQuery
                ->whereIn('categories.slug', $filter->category_slugs));
        }

        if ($filter->min_price || $filter->max_price || $filter->with_discounts) {
            $query->addSelect('products.*')
                ->join('product_prices', 'products.id', '=', 'product_prices.product_id');

            if ($filter->min_price !== null) {
                $query->where('product_prices.min_price', '>=', $filter->min_price);
            }

            if ($filter->max_price !== null) {
                $query->where('product_prices.min_price', '<=', $filter->max_price);
            }

            if ($filter->with_discounts) {
                $query->where('product_prices.has_discount', true);
            }
        }

        if ($this->appliesCapacityFilter && $filter->near_capacity_only) {
            $query = ProductAvailabilityFilter::applyNearCapacity($query, $this->capacityThreshold());
        }

        if ($filter->difficulty_level) {
            $types = collect($this->productableType !== null
                ? [$this->productableType->value]
                : ProductableEnum::getAllValues())
                ->reject(fn (string $type): bool => $type === ProductableEnum::BUNDLE->value)
                ->values()
                ->all();

            if ($types === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHasMorph('productable', $types, fn (Builder $productableQuery): Builder => $productableQuery
                    ->where('difficulty_level', $filter->difficulty_level));
            }
        }

        if ($filter->fulfillment_types) {
            $query->whereHas('productDeliveryOptions', fn (Builder $optionQuery): Builder => $optionQuery
                ->whereIn('fulfillment_type', $filter->fulfillment_types));
        }

        return $query;
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applyDatabaseAvailabilityClauses(Builder $query): Builder
    {
        $filter = $this->filter;

        if ($filter?->is_available_now) {
            return ProductAvailabilityFilter::applyAvailableNow($query);
        }

        if ($filter?->registration_starts_after || $filter?->registration_ends_before) {
            $query = ProductAvailabilityFilter::applyRegistrationWindow(
                $query,
                $filter->registration_starts_after,
                $filter->registration_ends_before,
            );
        }

        if ($filter?->availability_status) {
            return ProductAvailabilityFilter::applyEventStatus($query, AvailabilityStatusEnum::from($filter->availability_status));
        }

        if ($filter?->available_from || $filter?->available_to) {
            return ProductAvailabilityFilter::applyAvailabilityWindow($query, $filter->available_from, $filter->available_to);
        }

        $query = ProductAvailabilityFilter::applyEventNotEnded($query);

        return ProductAvailabilityFilter::applyContentAvailableNow($query);
    }

    /**
     * @return list<string>
     */
    private function typesenseAvailabilityClauses(): array
    {
        $filter = $this->filter;
        $now    = $this->now->timestamp;

        if ($filter?->is_available_now) {
            return [
                "earliest_registration_start_ts:<={$now}",
                "latest_registration_end_ts:>={$now}",
                "earliest_availability_start_ts:<={$now}",
                "latest_availability_end_ts:>={$now}",
            ];
        }

        $clauses = [];

        if ($filter?->registration_starts_after) {
            $clauses[] = 'latest_registration_end_ts:>='.$filter->registration_starts_after->timestamp;
        }

        if ($filter?->registration_ends_before) {
            $clauses[] = 'earliest_registration_start_ts:<='.$filter->registration_ends_before->timestamp;
        }

        if ($filter?->availability_status) {
            $today = $this->now->copy()->startOfDay()->timestamp;

            return [
                ...$clauses,
                ...match ($filter->availability_status) {
                    AvailabilityStatusEnum::PAST->value     => ["latest_event_ended_ts:<{$today}"],
                    AvailabilityStatusEnum::UPCOMING->value => ["earliest_event_start_ts:>{$today}"],
                    AvailabilityStatusEnum::ONGOING->value  => [
                        "earliest_event_start_ts:<={$today}",
                        "latest_event_ended_ts:>={$today}",
                    ],
                    default => [],
                },
            ];
        }

        if ($filter?->available_from || $filter?->available_to) {
            if ($filter->available_from) {
                $clauses[] = 'latest_availability_end_ts:>='.$filter->available_from->timestamp;
            }

            if ($filter->available_to) {
                $clauses[] = 'earliest_availability_start_ts:<='.$filter->available_to->timestamp;
            }

            return $clauses;
        }

        return [
            ...$clauses,
            "earliest_availability_start_ts:<={$now}",
            "latest_availability_end_ts:>={$now}",
            'latest_event_ended_ts:>='.$this->now->copy()->startOfDay()->timestamp,
        ];
    }
}
