<?php

declare(strict_types=1);

namespace App\Query;

use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\Product\ProductSortFieldEnum;
use App\Models\Product;
use App\Services\ProductSearch;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

/**
 * Shop Product Query Service
 *
 * This service handles all product querying logic for the shop frontend.
 * It uses a deferred execution pattern for relationship constraints to optimize
 * complex queries by consolidating them into single WHERE HAS / WHERE HAS MORPH clauses.
 */
final class ProductQueryService
{
    /** @deprecated Use ProductSortFieldEnum::ALLOWED. */
    public const array allowedSortFields = ProductSortFieldEnum::ALLOWED;

    /** @var Builder<Product> */
    private Builder $query;

    /**
     * @var string[] Tracks which direct JOINs have been applied to prevent duplicates.
     */
    private array $appliedJoins = [];

    /**
     * @var array<string, list<Closure>> Collects relationship constraints to be applied later.
     */
    private array $relationshipConstraints = [];

    /**
     * @var string[] The morphable types for the 'productable' relationship query.
     */
    private array $productableTypes;

    private bool $checkTermStatus = true;

    private bool $selectClauseModified = false;

    public function __construct()
    {
        $this->query = Product::query();
        // Default to searching across all productable types.
        $this->productableTypes = ProductableEnum::getAllValues();
    }

    public static function make(): self
    {
        return app(self::class);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function setQuery(Builder $query): self
    {
        if ($query->getModel() instanceof Product === false) { // defensive runtime guard
            throw new InvalidArgumentException('The provided query builder must be for the Product model.');
        }
        $this->query = $query;

        return $this;
    }

    // === PRESET METHODS FOR DTO-BASED LISTINGS ===

    /**
     * Get a paginated list of courses based on filter criteria.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function getCourseList(ProductListRequestData $requestData): LengthAwarePaginator
    {
        $requestData->type = ProductableEnum::COURSE->value;

        return $this->globalSearch($requestData);
    }

    /**
     * Get a paginated list of seminars based on filter criteria.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function getSeminarList(ProductListRequestData $requestData): LengthAwarePaginator
    {
        $requestData->type = ProductableEnum::SEMINAR->value;

        return $this->globalSearch($requestData);
    }

    /**
     * Get a paginated list of digital assets based on filter criteria.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function getDigitalAssetList(ProductListRequestData $requestData): LengthAwarePaginator
    {
        $requestData->type = ProductableEnum::DIGITAL_ASSET->value;

        return $this->globalSearch($requestData);
    }

    /**
     * Perform a global search across all available product types.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function globalSearchProductsDatabase(ProductListRequestData $requestData): LengthAwarePaginator
    {
        return app(ProductSearch::class)->searchDatabase($requestData);
    }

    /**
     * Smart search with automatic fallback.
     * Uses Typesense if available, falls back to database search.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function globalSearch(ProductListRequestData $requestData): LengthAwarePaginator
    {
        return app(ProductSearch::class)->search($requestData);
    }

    /**
     * @codeCoverageIgnore
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function globalSearchProductsScout(ProductListRequestData $requestData): LengthAwarePaginator
    {
        return app(ProductSearch::class)->searchScout($requestData);
    }

    /**
     * Start with available products (shop-ready).
     */
    public function availableProducts(): self
    {
        $this->applyAvailabilityFilters();

        return $this;
    }

    /**
     * Filter products to only those that are available for purchase/enrollment right now.
     */
    public function availableNow(): self
    {
        $this->query = ProductAvailabilityFilter::applyAvailableNow($this->query);

        return $this;
    }

    /**
     * Filter products based on their registration window.
     *
     * @param  Carbon|string|null  $from  Find products where registration starts on or after this date.
     * @param  Carbon|string|null  $to  Find products where registration ends on or before this date.
     */
    /**
     * Filter products based on their registration window.
     * Returns products that overlap with the specified date range or have no date restrictions.
     *
     * @param  Carbon|string|null  $from  Find products with registration starting on or after this date.
     * @param  Carbon|string|null  $to  Find products with registration ending on or before this date.
     */
    public function registrationWindow(Carbon|string|null $from = null, Carbon|string|null $to = null): self
    {
        if (! $from && ! $to) {
            return $this;
        }

        $this->query = ProductAvailabilityFilter::applyRegistrationWindow(
            $this->query,
            $from instanceof Carbon ? $from : ($from !== null ? Carbon::parse($from) : null),
            $to instanceof Carbon ? $to : ($to !== null ? Carbon::parse($to) : null),
        );

        return $this;
    }

    public function availabilityStatus(AvailabilityStatusEnum $availabilityStatus): self
    {
        return $this->eventStatus($availabilityStatus);
    }

    /**
     * Classify products by event temporal state with fallback to PDO availability windows.
     *
     * Event dates (products.event_start_at / products.event_ended_at) take priority.
     * When both event dates are null, falls back to ProductDeliveryOption available_from/available_to.
     */
    public function eventStatus(?AvailabilityStatusEnum $status): static
    {
        if ($status === null) {
            return $this;
        }

        $this->query = ProductAvailabilityFilter::applyEventStatus($this->query, $status);

        return $this;
    }

    /**
     * Constrain to products whose event has not ended yet.
     * Includes products with no event date (event_ended_at IS NULL) and
     * products where the event end date is today or in the future.
     */
    public function eventNotEnded(): static
    {
        $this->query = ProductAvailabilityFilter::applyEventNotEnded($this->query);

        return $this;
    }

    /**
     * Filter products based on their content availability window.
     * Returns products that overlap with the specified date range or have no date restrictions.
     *
     * @param  Carbon|string|null  $from  Find products available on or after this date.
     * @param  Carbon|string|null  $to  Find products available on or before this date.
     */
    public function availabilityWindow(Carbon|string|null $from = null, Carbon|string|null $to = null): self
    {
        if (! $from && ! $to) {
            return $this;
        }

        $this->query = ProductAvailabilityFilter::applyAvailabilityWindow(
            $this->query,
            $from instanceof Carbon ? $from : ($from !== null ? Carbon::parse($from) : null),
            $to instanceof Carbon ? $to : ($to !== null ? Carbon::parse($to) : null),
        );

        return $this;
    }

    /**
     * exclude products that are fully booked/sold out.
     */
    public function withoutFullProducts(): self
    {
        $this->query->whereHas('productDeliveryOptions', function (Builder $optionQuery): void {
            $optionQuery->where('status', PublicationStatusEnum::PUBLISHED)
                ->where(function (Builder $capacityQuery): void {
                    $capacityQuery->whereNull('capacity')
                        ->orWhereColumn('capacity', '>', 'enrolled_count');
                });
        });

        return $this;
    }

    /**
     * Filter to products that have at least one delivery option near capacity.
     */
    public function nearingCapacity(float $threshold = 0.8): self
    {
        $this->query = ProductAvailabilityFilter::applyNearCapacity($this->query, $threshold);

        return $this;
    }

    /**
     * Filter by product type. Overwrites existing productableTypes.
     */
    public function ofType(ProductableEnum $type): self
    {
        $this->productableTypes = [$type->value];

        return $this;
    }

    /**
     * Filter by multiple product types.
     *
     * @param  ProductableEnum[]  $types
     */
    public function ofTypes(array $types): self
    {
        $this->productableTypes = array_map(fn (ProductableEnum $type) => $type->value, $types);

        return $this;
    }

    // === DEFERRED RELATIONSHIP FILTERS ===

    /**
     * Filter by categories.
     *
     * @param  int[]  $categorySlugs
     */
    public function inCategories(array $categorySlugs): self
    {
        if (empty($categorySlugs)) {
            return $this;
        }

        return $this->addRelationshipConstraint('categories', function ($q) use ($categorySlugs): void {
            $q->whereIn('categories.slug', $categorySlugs);
        });
    }

    /**
     * @param  int[]  $categoryIds
     */
    public function inCategoryIds(array $categoryIds): self
    {
        if (empty($categoryIds)) {
            return $this;
        }

        return $this->addRelationshipConstraint('categories', function ($q) use ($categoryIds): void {
            $q->whereIn('categories.id', $categoryIds);
        });
    }

    /**
     * @param  string[]  $categorySlugs
     */
    public function goodForStart(array $categorySlugs): self
    {
        if (empty($categorySlugs)) {
            return $this;
        }

        return $this->addRelationshipConstraint('productable',
            function (Builder $productableQuery) use ($categorySlugs): void {
                $productableQuery->whereHas('categories', function (Builder $categoryQuery) use ($categorySlugs): void {
                    $categoryQuery
                        ->whereIn('categories.slug', $categorySlugs)
                        ->where('categorizables.good_for_start', true);
                });
            });
    }

    /**
     * Filter by instructor/teacher.
     */
    public function byInstructor(int $instructorId): self
    {
        return $this->addRelationshipConstraint('productDeliveryOptions.teachers', function ($q) use ($instructorId): void {
            $q->where('teachers.id', $instructorId);
        });
    }

    /**
     * Filter by course difficulty level. (Applies to 'productable')
     */
    public function byCourseLevel(CourseDifficultyLevelEnum $difficulty_level): self
    {
        return $this->addRelationshipConstraint('productable', function ($q) use ($difficulty_level): void {
            $q->where('difficulty_level', $difficulty_level->value);
        });
    }

    /**
     * Filter by fulfillment type. (Applies to 'productable')
     *
     * @param  string[]  $fulfillmentTypes
     */
    public function byFulfillmentTypes(array $fulfillmentTypes): self
    {
        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q) use ($fulfillmentTypes): void {
            $q->whereIn('fulfillment_type', $fulfillmentTypes);
        });
    }

    public function search(?string $searchTerm): self
    {
        if (empty($searchTerm)) {
            return $this;
        }
        $this->ensureBaseSelects();
        $this->query->selectScore(table: 'products');
        $this->query->where(function (Builder $q) use ($searchTerm): void {
            // Use the new fullTextSearch macro which automatically detects the database driver
            // and falls back to appropriate methods (PGroonga for PostgreSQL, MATCH AGAINST for MySQL, etc.)
            $q->withPgroonga()->fullTextSearch(['name', 'short_name', 'short_description', 'slug'], $searchTerm);

            foreach ($this->productableTypes as $type) {
                $q->orWhereHasMorph('productable', [$type], function (Builder $sq) use ($searchTerm, $type): void {
                    $searchColumns = ['full_name', 'short_name', 'description', 'slug'];

                    if (in_array($type, [ProductableEnum::SEMINAR->value, ProductableEnum::DIGITAL_ASSET->value])) {
                        $searchColumns[] = 'keywords';
                    }

                    $sq->withPgroonga()->fullTextSearch($searchColumns, $searchTerm);
                });
            }
        });

        return $this;
    }

    public function featured(): self
    {
        $this->query->where('is_featured', true);

        return $this;
    }

    public function priceRange(?int $minPrice = null, ?int $maxPrice = null): self
    {
        if ($minPrice === null && $maxPrice === null) {
            return $this;
        }

        $this->applyPriceJoinOnce();

        if ($minPrice !== null) {
            $this->query->where('product_prices.min_price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $this->query->where('product_prices.min_price', '<=', $maxPrice);
        }

        return $this;
    }

    public function withDiscounts(): self
    {
        $this->applyPriceJoinOnce();
        $this->query->where('product_prices.has_discount', true);

        return $this;
    }

    public function withPrices(): self
    {
        $this->applyPriceJoinOnce();

        return $this;
    }

    // === SORTING AND EXECUTION ===

    public function sortBy(string $field, string $direction = 'desc'): self
    {

        if (! in_array($field, self::allowedSortFields) || ! in_array($direction, ['asc', 'desc'])) {
            return $this;
        }

        if ($field === 'price') {
            $this->applyPriceJoinOnce();
            $this->query->orderBy('product_prices.min_price', $direction);
        } elseif ($field === 'capacity_utilization') {
            $this->sortByCapacityUtilization();
        } else {
            $this->query->orderBy("products.{$field}", $direction);
        }

        return $this;
    }

    public function popular(): self
    {
        $this->query->withCount('orderItems')->orderBy('order_items_count', 'desc');

        return $this;
    }

    /**
     * Get paginated results. This is a terminal method that executes the query.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $this->applyDeferredConstraints();

        return $this->query->paginate($perPage)->withQueryString();
    }

    /**
     * Get a collection of results. This is a terminal method.
     *
     * @return Collection<int, Product>
     */
    public function get(): Collection
    {
        $this->applyDeferredConstraints();

        return $this->query->get();
    }

    /**
     * Get the first result. This is a terminal method.
     */
    public function first(): ?Product
    {
        $this->applyDeferredConstraints();

        return $this->query->first();
    }

    public function limit(int $limit): self
    {
        $this->query->limit($limit);

        return $this;
    }

    // === RELATIONSHIP LOADING (EAGER LOADING) ===

    public function forListing(): self
    {
        $this->query = ProductListing::forListing($this->query);

        return $this;
    }

    public function forDetail(): self
    {
        $this->query = ProductListing::forDetail($this->query);

        return $this;
    }

    /**
     * @return Builder<Product>
     */
    public function getQuery(): Builder
    {
        $this->applyDeferredConstraints();

        return $this->query;
    }

    /**
     * Filter products to only those whose content is currently available (ignores registration window).
     * This checks only the available_from/available_to dates, not registration dates.
     */
    public function contentAvailableNow(): self
    {
        $this->query = ProductAvailabilityFilter::applyContentAvailableNow($this->query);

        return $this;
    }

    /**
     * Sort products by how close they are to capacity.
     * Near-capacity products are prioritized, then higher utilization comes first.
     *
     * Uses a LEFT JOIN LATERAL to compute both the threshold flag and max ratio
     * in a single PDO scan per product, instead of two correlated subqueries.
     */
    public function sortByCapacityUtilization(float $threshold = 0.8): self
    {
        $this->ensureBaseSelects();
        $this->query = ProductListing::sortByCapacityUtilization($this->query, $threshold);

        return $this;
    }

    // === PRIVATE HELPER METHODS ===

    /**
     * Apply core availability filters to the query.
     */
    private function applyAvailabilityFilters(): void
    {
        $this->query = ProductAvailabilityFilter::applyPublishedAndVisible($this->query);
        $this->query = ProductAvailabilityFilter::applyHasPublishedDeliveryOption($this->query);
        $this->query = ProductAvailabilityFilter::applyPublishedProductable($this->query);

        if (config('products.availability.use_denormalized')) {
            $this->query->whereIn('products.productable_type', $this->productableTypes);
        }

        if ($this->checkTermStatus) {
            $this->query = ProductAvailabilityFilter::applyActiveTerm($this->query);
        }

    }

    /**
     * Adds a closure to the relationship constraints collector for later execution.
     */
    private function addRelationshipConstraint(string $relationship, Closure $callback): self
    {
        $this->relationshipConstraints[$relationship][] = $callback;

        return $this;
    }

    /**
     * Applies all collected constraints to the query builder right before execution.
     */
    private function applyDeferredConstraints(): void
    {
        foreach ($this->relationshipConstraints as $relationship => $callbacks) {
            // we should never hit this, but just in case
            // @codeCoverageIgnoreStart
            if (empty($callbacks)) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $consolidatedCallback = function ($q) use ($callbacks): void {
                foreach ($callbacks as $callback) {
                    $callback($q);
                }
            };

            if ($relationship === 'productable') {
                $this->query->whereHasMorph('productable', $this->productableTypes, $consolidatedCallback);
            } else {
                $this->query->whereHas($relationship, $consolidatedCallback);
            }
        }

        $this->relationshipConstraints = [];
    }

    /**
     * Ensures the product_prices table is joined only once for performance.
     */
    private function applyPriceJoinOnce(): void
    {
        if (! in_array('price_filter', $this->appliedJoins)) {
            $this->ensureBaseSelects();

            $this->query->addSelect([
                'product_prices.product_id',
                'product_prices.min_price',
                'product_prices.min_original_price',
                'product_prices.max_price',
                'product_prices.max_original_price',
                'product_prices.has_discount',
                'product_prices.has_featured_price',
                'product_prices.has_prepayment',
                'product_prices.discount_percentage',
                'product_prices.highest_discount_amount',
            ]);
            $this->query->join('product_prices', 'products.id', '=', 'product_prices.product_id');
            $this->appliedJoins[] = 'price_filter';
        }
    }

    private function ensureBaseSelects(): void
    {
        if (! $this->selectClauseModified) {
            $this->query->select('products.*');
            $this->selectClauseModified = true;
        }
    }
}
