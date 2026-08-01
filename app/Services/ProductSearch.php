<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Shop\Product\Course\ProductFilterData;
use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\Product\ProductSortFieldEnum;
use App\Models\Product;
use App\Query\ProductAvailabilityFilter;
use App\Query\ProductListing;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

final class ProductSearch
{
    public function __construct(
        private readonly ?Closure $typesenseAvailability = null,
        private readonly ?Closure $scoutSearch = null,
    ) {}

    public function search(ProductListRequestData $requestData): LengthAwarePaginator
    {
        if ($this->isTypesenseAvailable()) {
            try {
                return $this->scoutSearch !== null
                    ? ($this->scoutSearch)($requestData)
                    : $this->searchScout($requestData);
            } catch (Exception $exception) {
                Log::warning('Typesense product search failed, falling back to database', [
                    'query' => $requestData->q,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $this->searchDatabase($requestData);
    }

    public function searchDatabase(ProductListRequestData $requestData): LengthAwarePaginator
    {
        $query = Product::query();

        if ($requestData->type !== null) {
            $query->ofType(ProductableEnum::from($requestData->type));
        }

        $query             = ProductAvailabilityFilter::applyPublishedAndVisible($query);
        $query             = ProductAvailabilityFilter::applyHasPublishedDeliveryOption($query);
        $query             = ProductAvailabilityFilter::applyPublishedProductable($query);
        $query             = ProductAvailabilityFilter::applyActiveTerm($query);
        $query             = ProductListing::forListing($query);
        $capacityThreshold = $requestData->filter?->capacity_threshold ?? 0.8;

        if ($requestData->q) {
            $query->select('products.*')->selectScore(table: 'products');
            $query->search($requestData->q);
        }

        if ($requestData->filter) {
            $filter = $requestData->filter;

            if ($filter->category_slugs) {
                $query->whereHas('categories', fn (Builder $categoryQuery): Builder => $categoryQuery
                    ->whereIn('categories.slug', $filter->category_slugs));
            }

            if ($filter->min_price || $filter->max_price || $filter->with_discounts || $requestData->sortBy === 'price') {
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
            if ($filter->near_capacity_only) {
                $query = ProductAvailabilityFilter::applyNearCapacity($query, $capacityThreshold);
            }
            if ($filter->difficulty_level) {
                $types = $requestData->type !== null
                    ? [ProductableEnum::from($requestData->type)->value]
                    : ProductableEnum::getAllValues();
                $query->whereHasMorph('productable', $types, fn (Builder $productableQuery): Builder => $productableQuery
                    ->where('difficulty_level', $filter->difficulty_level));
            }
            if ($filter->fulfillment_types) {
                $query->whereHas('productDeliveryOptions', fn (Builder $optionQuery): Builder => $optionQuery
                    ->whereIn('fulfillment_type', $filter->fulfillment_types));
            }

            $this->applyDatabaseAvailabilityFilters($query, $filter);
        } else {
            $query = ProductAvailabilityFilter::applyEventNotEnded($query);
            $query = ProductAvailabilityFilter::applyContentAvailableNow($query);
        }

        if ($requestData->sortBy === 'capacity_utilization') {
            return ProductListing::paginate(
                ProductListing::sortByCapacityUtilization($query, $capacityThreshold),
                $requestData->per_page,
            );
        }

        $isDefaultOrder = $requestData->sortBy === 'created_at' && $requestData->sortOrder === 'desc';

        if ($isDefaultOrder && $requestData->q) {
            $query->orderByScore();
        } elseif ($requestData->sortBy === 'price') {
            if ($requestData->filter === null) {
                $query->addSelect('products.*')
                    ->join('product_prices', 'products.id', '=', 'product_prices.product_id');
            }
            $query->orderBy('product_prices.min_price', $requestData->sortOrder);
        } else {
            $query = ProductListing::sortBy($query, $requestData->sortBy, $requestData->sortOrder);
        }

        return ProductListing::paginate($query, $requestData->per_page);
    }

    /** @codeCoverageIgnore */
    public function searchScout(ProductListRequestData $requestData): LengthAwarePaginator
    {
        if (! $this->isTypesenseAvailable()) {
            return $this->searchDatabase($requestData);
        }

        $query = Product::scoutSearch($requestData->q ?: '*')
            ->options([
                'query_by'  => 'embedding',
                'prefix'    => 'false',
                'num_typos' => '0',
            ]);

        $query->where('status', PublicationStatusEnum::PUBLISHED->value)
            ->where('is_visible', true)
            ->where('productable_status', PublicationStatusEnum::PUBLISHED->value)
            ->where('has_published_delivery_option', true)
            ->where('is_term_active', true);

        if ($requestData->type) {
            $query->where('productable_type', ProductableEnum::from($requestData->type)->value);
        }

        if ($requestData->filter) {
            $filter = $requestData->filter;

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

            if ($filter->near_capacity_only) {
                $query->where('max_capacity_utilization', [
                    '>=',
                    $filter->capacity_threshold ?? (float) config('products.availability.capacity_threshold', 0.8),
                ]);
            }
        }

        $this->applyScoutAvailabilityFilters($query, $requestData->filter ?? new ProductFilterData());

        if (in_array($requestData->sortBy, ProductSortFieldEnum::ALLOWED, true)) {
            $requestData->sortBy === 'capacity_utilization'
                ? $query->orderByDesc('max_capacity_utilization')
                : $query->orderBy($requestData->sortBy, $requestData->sortOrder);
        }

        return $query
            ->query(function (Builder $query): void {
                $query->forListing();
            })
            ->paginate($requestData->per_page)
            ->withQueryString();
    }

    private function applyDatabaseAvailabilityFilters(Builder $query, ProductFilterData $filter): void
    {
        if ($filter->is_available_now) {
            ProductAvailabilityFilter::applyAvailableNow($query);

            return;
        }

        if ($filter->registration_starts_after || $filter->registration_ends_before) {
            ProductAvailabilityFilter::applyRegistrationWindow(
                $query,
                $filter->registration_starts_after,
                $filter->registration_ends_before,
            );
        }

        if ($filter->availability_status) {
            ProductAvailabilityFilter::applyEventStatus($query, AvailabilityStatusEnum::from($filter->availability_status));

            return;
        }

        if ($filter->available_from || $filter->available_to) {
            ProductAvailabilityFilter::applyAvailabilityWindow($query, $filter->available_from, $filter->available_to);

            return;
        }

        ProductAvailabilityFilter::applyEventNotEnded($query);
        ProductAvailabilityFilter::applyContentAvailableNow($query);
    }

    /** @codeCoverageIgnore */
    private function applyScoutAvailabilityFilters(mixed $query, ProductFilterData $filter): void
    {
        if ($filter->is_available_now) {
            $now = now()->timestamp;
            $query->where('earliest_registration_start_ts', ['<=', $now])
                ->where('latest_registration_end_ts', ['>=', $now])
                ->where('earliest_availability_start_ts', ['<=', $now])
                ->where('latest_availability_end_ts', ['>=', $now]);

            return;
        }

        if ($filter->registration_starts_after) {
            $query->where('latest_registration_end_ts', ['>=', $filter->registration_starts_after->timestamp]);
        }
        if ($filter->registration_ends_before) {
            $query->where('earliest_registration_start_ts', ['<=', $filter->registration_ends_before->timestamp]);
        }

        if ($filter->availability_status) {
            $today = now()->startOfDay()->timestamp;

            match ($filter->availability_status) {
                AvailabilityStatusEnum::PAST->value     => $query->where('latest_event_ended_ts', ['<', $today]),
                AvailabilityStatusEnum::UPCOMING->value => $query->where('earliest_event_start_ts', ['>', $today]),
                AvailabilityStatusEnum::ONGOING->value  => $query
                    ->where('earliest_event_start_ts', ['<=', $today])
                    ->where('latest_event_ended_ts', ['>=', $today]),
            };

            return;
        }

        if ($filter->available_from || $filter->available_to) {
            if ($filter->available_from) {
                $query->where('latest_availability_end_ts', ['>=', $filter->available_from->timestamp]);
            }
            if ($filter->available_to) {
                $query->where('earliest_availability_start_ts', ['<=', $filter->available_to->timestamp]);
            }

            return;
        }

        $now = now()->timestamp;
        $query->where('earliest_availability_start_ts', ['<=', $now])
            ->where('latest_availability_end_ts', ['>=', $now])
            ->where('latest_event_ended_ts', ['>=', now()->startOfDay()->timestamp]);
    }

    private function isTypesenseAvailable(): bool
    {
        if ($this->typesenseAvailability !== null) {
            return (bool) ($this->typesenseAvailability)();
        }

        return config('scout.driver') === 'typesense'
            && ! empty(config('scout.typesense.client-settings.api_key'))
            && ! app()->runningUnitTests();
    }
}
