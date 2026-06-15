<?php

declare(strict_types=1);

namespace App\Query;

use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\TermStatusEnum;
use App\Models\Product;
use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    public const array allowedSortFields
        = [
            'created_at', 'updated_at', 'name', 'short_name', 'price', 'capacity_utilization',
        ];

    private Builder $query;

    /**
     * @var string[] Tracks which direct JOINs have been applied to prevent duplicates.
     */
    private array $appliedJoins = [];

    /**
     * @var Closure Collects relationship constraints to be applied later.
     */
    private array $relationshipConstraints = [];

    /**
     * @var string[] The morphable types for the 'productable' relationship query.
     */
    private array $productableTypes = [];

    private bool $includeFullProducts = true;

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

    public function setQuery(Builder $query): self
    {
        if ($query->getModel() instanceof Product === false) {
            throw new InvalidArgumentException('The provided query builder must be for the Product model.');
        }
        $this->query = $query;

        return $this;
    }

    // === PRESET METHODS FOR DTO-BASED LISTINGS ===

    /**
     * Get a paginated list of courses based on filter criteria.
     */
    public function getCourseList(ProductListRequestData $requestData): LengthAwarePaginator
    {
        return $this
            ->ofType(ProductableEnum::COURSE)
            ->globalSearch($requestData);
    }

    /**
     * Get a paginated list of seminars based on filter criteria.
     */
    public function getSeminarList(ProductListRequestData $requestData): LengthAwarePaginator
    {
        return $this
            ->ofType(ProductableEnum::SEMINAR)
            ->globalSearch($requestData);
    }

    /**
     * Get a paginated list of digital assets based on filter criteria.
     */
    public function getDigitalAssetList(ProductListRequestData $requestData): LengthAwarePaginator
    {
        return $this
            ->ofType(ProductableEnum::DIGITAL_ASSET)
            ->globalSearch($requestData);
    }

    /**
     * Perform a global search across all available product types.
     */
    public function globalSearchProductsDatabase(ProductListRequestData $requestData): LengthAwarePaginator
    {
        // Keeps the default of all productableTypes
        $this->availableProducts()->forListing();
        $capacityThreshold = $requestData->filter?->capacity_threshold ?? 0.8;

        if ($requestData->q) {
            $this->search($requestData->q);
        }
        if ($requestData->type) {
            $this->productableTypes = [ProductableEnum::from($requestData->type)->value];
        }
        if ($requestData->filter) {
            $filter = $requestData->filter;

            if ($filter->category_slugs) {
                $this->inCategories($filter->category_slugs);
            }
            if ($filter->min_price || $filter->max_price) {
                $this->priceRange($filter->min_price, $filter->max_price);
            }
            if ($filter->with_discounts) {
                $this->withDiscounts();
            }
            if ($filter->near_capacity_only) {
                $this->nearingCapacity($capacityThreshold);
            }
            if ($filter->difficulty_level) {
                $this->byCourseLevel(CourseDifficultyLevelEnum::from($filter->difficulty_level));
            }
            if ($filter->fulfillment_types) {
                $this->byFulfillmentTypes($filter->fulfillment_types);
            }

            // Apply consolidated availability/registration filters
            $this->applyDatabaseAvailabilityFilters($filter);

        } else {
            // Default: Exclude past events, only show currently available products
            $this->eventNotEnded()->contentAvailableNow();
        }
        if ($requestData->sortBy === 'capacity_utilization') {
            return $this
                ->sortByCapacityUtilization($capacityThreshold)
                ->paginate($requestData->per_page);
        }

        $isDefaultOrder = $requestData->sortBy === 'created_at' && $requestData->sortOrder === 'desc';

        return $this
            ->when($isDefaultOrder && $requestData->q,
                fn ($q) => $q->query->orderByScore(),
                fn ($q) => $q->sortBy($requestData->sortBy, $requestData->sortOrder)
            )
            ->paginate($requestData->per_page);
    }

    /**
     * Smart search with automatic fallback.
     * Uses Typesense if available, falls back to database search.
     */
    public function globalSearch(ProductListRequestData $requestData): LengthAwarePaginator
    {
        // we can only test this manually, so ignore for code coverage
        // @codeCoverageIgnoreStart
        if ($this->isTypesenseAvailable()) {
            try {
                return $this->globalSearchProductsScout($requestData);
            } catch (Exception $e) {
                Log::warning('Typesense product search failed, falling back to database', [
                    'query' => $requestData->q,
                    'error' => $e->getMessage(),
                ]);
                // Fall through to database search
            }
        }
        // @codeCoverageIgnoreEnd

        return $this->globalSearchProductsDatabase($requestData);
    }

    /**
     * @codeCoverageIgnore
     */
    public function globalSearchProductsScout(ProductListRequestData $requestData): LengthAwarePaginator
    {
        if ($requestData->sortBy === 'capacity_utilization' || $requestData->filter?->near_capacity_only
                                                            || $requestData->filter?->capacity_threshold
        ) {
            return $this->globalSearchProductsDatabase($requestData);
        }

        if (config('scout.driver') !== 'typesense') {
            return $this->globalSearchProductsDatabase($requestData);
        }

        // Build the search query with the search term if provided
        $searchTerm = $requestData->q ?: '*';
        $query      = Product::search($searchTerm)
            ->options([
                'query_by' => 'embedding',
            ]);

        // Apply core availability filters (matching globalSearchProductsDatabase)
        $query->where('status', PublicationStatusEnum::PUBLISHED->value);
        $query->where('is_visible', true);
        $query->where('productable_status', PublicationStatusEnum::PUBLISHED->value);
        $query->where('has_published_delivery_option', true);
        $query->where('is_term_active', true);

        // Apply product type filter if specified, otherwise search across all types
        if ($requestData->type) {
            $query->where('productable_type', ProductableEnum::from($requestData->type)->value);
        }

        if ($requestData->filter) {
            $filter = $requestData->filter;

            // Category filter: use category_slugs (array)
            if ($filter->category_slugs && ! empty($filter->category_slugs)) {
                foreach ($filter->category_slugs as $slug) {
                    $query->where('category_slugs', $slug);
                }
            }

            // Difficulty level filter
            if ($filter->difficulty_level) {
                $query->where('difficulty_level', $filter->difficulty_level);
            }

            // Fulfillment types filter: use fulfillment_types (array)
            if ($filter->fulfillment_types && ! empty($filter->fulfillment_types)) {
                foreach ($filter->fulfillment_types as $type) {
                    $query->where('fulfillment_types', $type);
                }
            }

            // Price range filter using Typesense filter_by options
            if ($filter->min_price || $filter->max_price) {
                if ($filter->min_price && $filter->max_price) {
                    $query->options(['filter_by' => "price:[{$filter->min_price}..{$filter->max_price}]"]);
                } elseif ($filter->min_price) {
                    $query->options(['filter_by' => "price:>={$filter->min_price}"]);
                } elseif ($filter->max_price) {
                    $query->options(['filter_by' => "price:<={$filter->max_price}"]);
                }
            }

            // Discount filter
            if ($filter->with_discounts) {
                $query->where('has_discount', true);
            }

            // Available now filter: check if all date windows allow current date
            // Apply consolidated availability/registration filters
            $this->applyScoutAvailabilityFilters($query, $filter);
        }

        // Apply Sorting
        if (in_array($requestData->sortBy, self::allowedSortFields)) {
            $query->orderBy($requestData->sortBy, $requestData->sortOrder);
        }

        return $query
            ->query(function ($query) {
                $query->with([
                    'vendor:id,name',
                    'categories:id,name,slug',
                    'productable:id,thumbnail_url,default_teacher_info',
                    'productDeliveryOptions' => function ($q) {
                        $q->where('status', PublicationStatusEnum::PUBLISHED)
                            ->with([
                                'productDeliveryOptionDiscountPrice',
                                'teachers:id,first_name,last_name,gender,uuid,avatar_url,rate',
                            ]);
                    },
                ]);
            })
            ->paginate($requestData->per_page)
            ->withQueryString();
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
        $now = now();

        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q) use ($now) {
            // Check registration window is active
            $q->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('registration_start_date')
                    ->orWhere('registration_start_date', '<=', $now->startOfDay());
            })->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('registration_end_date')
                    ->orWhere('registration_end_date', '>=', $now->endOfDay());
            });

            // Check availability window is active
            $q->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('available_from')
                    ->orWhere('available_from', '<=', $now->startOfDay());
            })->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('available_to')
                    ->orWhere('available_to', '>=', $now->endOfDay());
            });
        });
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

        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q) use ($from, $to) {
            // Check for overlap: registration_start_date <= to AND registration_end_date >= from
            // Also include products with NULL dates (no restrictions)
            $q->where(function ($subQ) use ($from, $to) {
                $subQ->whereNull('registration_start_date')
                    ->orWhereNull('registration_end_date')
                    ->orWhere(function ($dateQ) use ($from, $to) {
                        if ($from && $to) {
                            $dateQ->where('registration_start_date', '<=', $to->endOfDay())
                                ->where('registration_end_date', '>=', $from->startOfDay());
                        } elseif ($from) {
                            $dateQ->where('registration_end_date', '>=', $from->startOfDay());
                        } elseif ($to) {
                            $dateQ->where('registration_start_date', '<=', $to->endOfDay());
                        }
                    });
            });
        });
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

        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q) use ($status) {
            $q->where(function ($inner) use ($status) {
                match ($status) {
                    AvailabilityStatusEnum::PAST => $inner->where(function ($sq): void {
                        // Primary: event_ended_at < today
                        $sq->where('products.event_ended_at', '<', today()->startOfDay());
                        // Fallback: available_to < today for products without event dates
                        $sq->orWhere(function ($inner2): void {
                            $inner2->whereNull('products.event_ended_at')
                                ->where('available_to', '<', today()->startOfDay());
                        });
                    }),
                    AvailabilityStatusEnum::UPCOMING => $inner->where(function ($sq): void {
                        $sq->where('products.event_start_at', '>', today()->startOfDay());
                        $sq->orWhere(function ($inner2): void {
                            $inner2->whereNull('products.event_start_at')
                                ->where('available_from', '>', today()->startOfDay());
                        });
                    }),
                    AvailabilityStatusEnum::ONGOING => $inner->where(function ($sq): void {
                        $sq->where(function ($inRange): void {
                            $inRange->whereNotNull('products.event_start_at')
                                ->where('products.event_start_at', '<=', today()->startOfDay())
                                ->whereNotNull('products.event_ended_at')
                                ->where('products.event_ended_at', '>=', today()->startOfDay());
                        });
                        $sq->orWhere(function ($fallback): void {
                            $fallback->whereNull('products.event_start_at')
                                ->whereNull('products.event_ended_at')
                                ->where('available_from', '<=', today()->startOfDay())
                                ->where(function ($availTo): void {
                                    $availTo->whereNull('available_to')
                                        ->orWhere('available_to', '>=', today()->startOfDay());
                                });
                        });
                    }),
                };
            });
        });
    }

    /**
     * Constrain to products whose event has not ended yet.
     * Includes products with no event date (event_ended_at IS NULL) and
     * products where the event end date is today or in the future.
     */
    public function eventNotEnded(): static
    {
        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q): void {
            $q->where(function ($inner): void {
                $inner->whereNull('products.event_ended_at')
                    ->orWhere('products.event_ended_at', '>=', today()->startOfDay());
            });
        });
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

        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q) use ($from, $to) {
            // Check for overlap: available_from <= to AND available_to >= from
            // Also include products with NULL dates (no restrictions)
            $q->where(function ($subQ) use ($from, $to) {
                $subQ->whereNull('available_from')
                    ->orWhereNull('available_to')
                    ->orWhere(function ($dateQ) use ($from, $to) {
                        if ($from && $to) {
                            $dateQ->where('available_from', '<=', $to->endOfDay())
                                ->where('available_to', '>=', $from->startOfDay());
                        } elseif ($from) {
                            $dateQ->where('available_to', '>=', $from->startOfDay());
                        } elseif ($to) {
                            $dateQ->where('available_from', '<=', $to->endOfDay());
                        }
                    });
            });
        });
    }

    /**
     * exclude products that are fully booked/sold out.
     */
    public function withoutFullProducts(): self
    {
        $this->includeFullProducts = false;

        return $this;
    }

    /**
     * Filter to products that have at least one delivery option near capacity.
     */
    public function nearingCapacity(float $threshold = 0.8): self
    {
        $threshold = max(0.0, min(1.0, $threshold));

        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q) use ($threshold) {
            $q->whereNotNull('capacity')
                ->where('capacity', '>', 0)
                ->whereRaw('((enrolled_count * 1.0) / NULLIF(capacity, 0)) >= ?', [$threshold]);
        });
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
        $this->productableTypes = array_map(fn ($type) => $type->value, $types);

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

        return $this->addRelationshipConstraint('categories', function ($q) use ($categorySlugs) {
            $q->whereIn('categories.slug', $categorySlugs);
        });
    }

    public function inCategoryIds(array $categoryIds): self
    {
        if (empty($categoryIds)) {
            return $this;
        }

        return $this->addRelationshipConstraint('categories', function ($q) use ($categoryIds) {
            $q->whereIn('categories.id', $categoryIds);
        });
    }

    public function goodForStart(array $categorySlugs): self
    {
        if (empty($categorySlugs)) {
            return $this;
        }

        return $this->addRelationshipConstraint('productable',
            function (Builder $productableQuery) use ($categorySlugs) {
                $productableQuery->whereHas('categories', function (Builder $categoryQuery) use ($categorySlugs) {
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
        return $this->addRelationshipConstraint('productDeliveryOptions.teachers', function ($q) use ($instructorId) {
            $q->where('teachers.id', $instructorId);
        });
    }

    /**
     * Filter by course difficulty level. (Applies to 'productable')
     */
    public function byCourseLevel(CourseDifficultyLevelEnum $difficulty_level): self
    {
        return $this->addRelationshipConstraint('productable', function ($q) use ($difficulty_level) {
            $q->where('difficulty_level', $difficulty_level->value);
        });
    }

    /**
     * Filter by fulfillment type. (Applies to 'productable')
     */
    public function byFulfillmentTypes(array $fulfillmentTypes): self
    {
        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q) use ($fulfillmentTypes) {
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
        $this->query->where(function (Builder $q) use ($searchTerm) {
            // Use the new fullTextSearch macro which automatically detects the database driver
            // and falls back to appropriate methods (PGroonga for PostgreSQL, MATCH AGAINST for MySQL, etc.)
            $q->fullTextSearch(['name', 'short_name', 'short_description', 'slug'], $searchTerm);

            foreach ($this->productableTypes as $type) {
                $q->orWhereHasMorph('productable', [$type], function (Builder $sq) use ($searchTerm, $type) {
                    $searchColumns = ['full_name', 'short_name', 'description', 'slug'];

                    if (in_array($type, [ProductableEnum::SEMINAR->value, ProductableEnum::DIGITAL_ASSET->value])) {
                        $searchColumns[] = 'keywords';
                    }

                    $sq->fullTextSearch($searchColumns, $searchTerm);
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
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $this->applyDeferredConstraints();

        return $this->query->paginate($perPage)->withQueryString();
    }

    /**
     * Get a collection of results. This is a terminal method.
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
        $this->query->with([
            'vendor:id,name',
            'categories:id,name,slug',
            'productDeliveryOptions' => function ($q) {
                $q->where('status', PublicationStatusEnum::PUBLISHED)
                    ->with([
                        'productDeliveryOptionDiscountPrice',
                        'teachers:id,first_name,last_name,gender,uuid,avatar_url,rate',
                    ]);
            },
            'productable',
        ]);

        return $this;
    }

    public function forDetail(): self
    {
        $this->query->with([
            'vendor:id,name',
            'categories:id,name,slug',
            'productDeliveryOptions' => function ($q) {
                $q->where('status', PublicationStatusEnum::PUBLISHED)
                    ->with([
                        'productDeliveryOptionDiscountPrice',
                        'teachers:id,first_name,last_name,gender,uuid,avatar_url,rate',
                    ]);
            },
            'productableWithAllRelations',
        ]);

        return $this;
    }

    public function getQuery()
    {
        $this->applyDeferredConstraints();

        return $this->query;
    }

    /**
     * Filter products to only those whose content is currently available (ignores registration window).
     * This checks only the available_from/available_to dates, not registration dates.
     */
    private function contentAvailableNow(): self
    {
        $now = now();

        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q) use ($now) {
            // Check availability window is active
            $q->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('available_from')
                    ->orWhere('available_from', '<=', $now->startOfDay());
            })->where(function ($subQuery) use ($now) {
                $subQuery->whereNull('available_to')
                    ->orWhere('available_to', '>=', $now->endOfDay());
            });
        });
    }

    /**
     * Apply database availability/registration filters based on ProductFilterData.
     * Consolidates all filter logic to prevent duplication and conflicts.
     */
    private function applyDatabaseAvailabilityFilters(\App\Data\Shop\Product\Course\ProductFilterData $filter): void
    {
        // Early return: if is_available_now is set, it supersedes everything else
        if ($filter->is_available_now) {
            $this->availableNow();

            return;
        }

        // Handle registration window filters
        if ($filter->registration_starts_after || $filter->registration_ends_before) {
            $this->registrationWindow(
                $filter->registration_starts_after,
                $filter->registration_ends_before
            );
        }

        // Handle availability filters (mutually exclusive: status OR window OR default)
        if ($filter->availability_status) {
            $this->availabilityStatus(AvailabilityStatusEnum::from($filter->availability_status));

            return;
        }

        if ($filter->available_from || $filter->available_to) {
            $this->availabilityWindow(
                $filter->available_from,
                $filter->available_to
            );

            return;
        }

        // Default: Only show products whose content is currently available (ignores registration window)
        // Exclude products whose events have already ended
        $this->eventNotEnded()->contentAvailableNow();
    }

    /**
     * Apply Scout availability/registration filters based on ProductFilterData.
     * Parallel to applyDatabaseAvailabilityFilters but uses Scout timestamp fields.
     */
    private function applyScoutAvailabilityFilters($query, \App\Data\Shop\Product\Course\ProductFilterData $filter): void
    {
        // Early return: if is_available_now is set, it supersedes everything else
        if ($filter->is_available_now) {
            $now = now()->timestamp;
            $query->where('earliest_registration_start_ts', ['<=', $now]);
            $query->where('latest_registration_end_ts', ['>=', $now]);
            $query->where('earliest_availability_start_ts', ['<=', $now]);
            $query->where('latest_availability_end_ts', ['>=', $now]);

            return;
        }

        // Handle registration window filters
        if ($filter->registration_starts_after) {
            $timestamp = $filter->registration_starts_after->timestamp;
            $query->where('latest_registration_end_ts', ['>=', $timestamp]);
        }

        if ($filter->registration_ends_before) {
            $timestamp = $filter->registration_ends_before->timestamp;
            $query->where('earliest_registration_start_ts', ['<=', $timestamp]);
        }

        // Handle availability filters (mutually exclusive: status OR window OR default)
        if ($filter->availability_status) {
            $startOfDayTs = now()->startOfDay()->timestamp;
            match ($filter->availability_status) {
                AvailabilityStatusEnum::PAST->value => $query->where('latest_event_ended_ts',
                    ['<', $startOfDayTs]),
                AvailabilityStatusEnum::UPCOMING->value => $query->where('earliest_event_start_ts',
                    ['>', $startOfDayTs]),
                AvailabilityStatusEnum::ONGOING->value => $query
                    ->where('earliest_event_start_ts', ['<=', $startOfDayTs])
                    ->where('latest_event_ended_ts', ['>=', $startOfDayTs]),
            };

            return;
        }

        if ($filter->available_from || $filter->available_to) {
            // Custom availability window
            if ($filter->available_from) {
                $timestamp = $filter->available_from->timestamp;
                $query->where('latest_availability_end_ts', ['>=', $timestamp]);
            }
            if ($filter->available_to) {
                $timestamp = $filter->available_to->timestamp;
                $query->where('earliest_availability_start_ts', ['<=', $timestamp]);
            }

            return;
        }

        // Default: Only show products whose content is currently available (ignores registration window)
        // Exclude products whose events have already ended
        $now = now()->timestamp;
        $query->where('earliest_availability_start_ts', ['<=', $now]);
        $query->where('latest_availability_end_ts', ['>=', $now]);
        $query->where('latest_event_ended_ts', ['>=', now()->startOfDay()->timestamp]);
    }

    // === PRIVATE HELPER METHODS ===

    /**
     * Apply core availability filters to the query.
     */
    private function applyAvailabilityFilters(): void
    {
        $this->query
            ->where('products.status', PublicationStatusEnum::PUBLISHED)
            ->where('products.is_visible', true);

        // Filter by available delivery options
        $this->addRelationshipConstraint('productDeliveryOptions', function ($q) {
            $q->where('status', PublicationStatusEnum::PUBLISHED);

            if (! $this->includeFullProducts) {
                $q->where(function ($capacityQuery) {
                    $capacityQuery->whereNull('capacity')
                        ->orWhereColumn('capacity', '>', 'enrolled_count');
                });
            }
        });

        // Filter by the status of the related 'productable' model
        $this->addRelationshipConstraint('productable', function ($q) {
            $q->where('status', PublicationStatusEnum::PUBLISHED);
        });

        // Optional term status check
        if ($this->checkTermStatus) {
            $this->query->where(function ($q) {
                $q->whereNull('term_id')
                    ->orWhereHas('term', fn ($termQuery) => $termQuery->where('status', TermStatusEnum::ACTIVE));
            });
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

            $consolidatedCallback = function ($q) use ($callbacks) {
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

    /**
     * Sort products by how close they are to capacity.
     * Near-capacity products are prioritized, then higher utilization comes first.
     *
     * Uses a LEFT JOIN LATERAL to compute both the threshold flag and max ratio
     * in a single PDO scan per product, instead of two correlated subqueries.
     */
    private function sortByCapacityUtilization(float $threshold = 0.8): self
    {
        $threshold       = max(0.0, min(1.0, $threshold));
        $publishedStatus = PublicationStatusEnum::PUBLISHED->value;

        $this->ensureBaseSelects();

        $this->query->leftJoinLateral(
            DB::table('product_delivery_options AS pdo_lat')
                ->selectRaw('COALESCE(MAX((pdo_lat.enrolled_count * 1.0) / NULLIF(pdo_lat.capacity, 0)), 0) AS max_ratio')
                ->selectRaw('COALESCE(MAX(CASE WHEN ((pdo_lat.enrolled_count * 1.0) / NULLIF(pdo_lat.capacity, 0)) >= ? THEN 1 ELSE 0 END), 0) AS near_capacity_flag',
                    [$threshold])
                ->whereColumn('pdo_lat.product_id', 'products.id')
                ->where('pdo_lat.status', $publishedStatus)
                ->whereNotNull('pdo_lat.capacity')
                ->where('pdo_lat.capacity', '>', 0),
            'pdo_cap_stats'
        );

        $this->query
            ->orderByRaw('pdo_cap_stats.near_capacity_flag DESC')
            ->orderByRaw('pdo_cap_stats.max_ratio DESC');

        return $this;
    }

    /**
     * Check if Typesense is configured and available.
     */
    private function isTypesenseAvailable(): bool
    {
        static $available = null;

        if ($available === null) {
            $available = config('scout.driver') === 'typesense'
                && ! empty(config('scout.typesense.client-settings.api_key'))
                && ! app()->runningUnitTests();
        }

        return $available;
    }

    private function when(bool $condition, Closure $trueCallback, ?Closure $falseCallback = null): self
    {
        if ($condition) {
            $trueCallback($this);
        } elseif ($falseCallback) {
            $falseCallback($this);
        }

        return $this;
    }
}
