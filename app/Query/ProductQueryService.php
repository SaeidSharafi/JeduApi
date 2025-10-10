<?php

declare(strict_types=1);

namespace App\Query;

use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\TermStatusEnum;
use App\Models\Product;
use Closure;
use Exception;
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
    public const array allowedSortFields = ['created_at', 'updated_at', 'name', 'short_name', 'price'];

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

        if ($requestData->search) {
            $this->search($requestData->search);
        }
        if ($requestData->filter) {
            $filter = $requestData->filter;

            if ($filter->category_ids) {
                $this->inCategories($filter->category_ids);
            }
            if ($filter->min_price || $filter->max_price) {
                $this->priceRange($filter->min_price, $filter->max_price);
            }
            if ($filter->with_discounts) {
                $this->withDiscounts();
            }
            if ($filter->difficulty_level) {
                $this->byCourseLevel(CourseDifficultyLevelEnum::from($filter->difficulty_level));
            }
            if ($filter->fulfillment_types) {
                $this->byFulfillmentTypes($filter->fulfillment_types);
            }
            if ($filter->type) {
                // If a type is specified in a global search, we narrow the scope.
                $this->productableTypes = [ProductableEnum::from($filter->type)->value];
            }
        }
        $isDefaultOrder = $requestData->sortBy === 'created_at' && $requestData->sortOrder === 'desc';

        return $this
            ->when($isDefaultOrder && $requestData->search,
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
                \Illuminate\Support\Facades\Log::warning('Typesense product search failed, falling back to database', [
                    'query' => $requestData->search,
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
        if (config('scout.driver') !== 'typesense') {
            return $this->globalSearchProductsDatabase($requestData);
        }

        $query = Product::search($requestData->search)
            ->options([
                'query_by' => 'embedding',
            ]);

        $query->where('status', PublicationStatusEnum::PUBLISHED->value);
        $query->where('is_visible', true);
        $query->where('productable_status', PublicationStatusEnum::PUBLISHED->value);
        $query->where('has_published_delivery_option', true);
        $query->where('is_term_active', true);

        // This is a static filter for this method
        $query->where('productable_type', ProductableEnum::COURSE->value);

        if ($requestData->filter) {
            $filter = $requestData->filter;

            if ($filter->categorySlug) {
                $query->where('category_slugs', $filter->categorySlug);
            }
            if ($filter->difficulty_level) {
                $query->where('difficulty_level', $filter->difficulty_level);
            }
            if ($filter->fulfillment_type) {
                $query->where('fulfillment_types', $filter->fulfillment_type);
            }
            if ($filter->min_price || $filter->max_price) {
                // Build a Typesense filter string for ranges
                $priceFilters = [];
                // Price filters need to be applied via options() for Typesense
                if ($filter->min_price && $filter->max_price) {
                    $query->options(['filter_by' => "price:[{$filter->min_price}..{$filter->max_price}]"]);
                } elseif ($filter->min_price) {
                    $query->options(['filter_by' => "price:>={$filter->min_price}"]);
                } elseif ($filter->max_price) {
                    $query->options(['filter_by' => "price:<={$filter->max_price}"]);
                }
            }
        }

        // Apply Sorting
        if (in_array($requestData->sortBy, ['created_at', 'updated_at', 'price', 'name'])) {
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
                            ->with(['productDeliveryOptionDiscountPrice', 'teachers:id,first_name,last_name,gender']);
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
     * exclude products that are fully booked/sold out.
     */
    public function withoutFullProducts(): self
    {
        $this->includeFullProducts = false;

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
        $this->productableTypes = array_map(fn ($type) => $type->value, $types);

        return $this;
    }

    // === DEFERRED RELATIONSHIP FILTERS ===

    /**
     * Filter by categories.
     *
     * @param  int[]  $categoryIds
     */
    public function inCategories(array $categoryIds): self
    {
        if (empty($categoryIds)) {
            return $this;
        }

        return $this->addRelationshipConstraint('categories', function ($q) use ($categoryIds) {
            $q->whereIn('categories.id', $categoryIds);
        });
    }

    /**
     * Filter by single category slug.
     */
    public function inCategory(string $categorySlug): self
    {
        return $this->addRelationshipConstraint('categories', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
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
                    ->with(['productDeliveryOptionDiscountPrice', 'teachers:id,first_name,last_name,gender']);
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
                    ->with(['productDeliveryOptionDiscountPrice', 'teachers:id,first_name,last_name,gender']);
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
                    $capacityQuery->where('capacity', 0)
                        ->orWhereRaw('capacity > (SELECT COUNT(*) FROM enrollments WHERE product_delivery_option_id = product_delivery_options.id AND enrollment_status IN (?, ?))',
                            [EnrollmentStatusEnum::ACTIVE->value, EnrollmentStatusEnum::PENDING_PROVISIONING->value]
                        );
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
