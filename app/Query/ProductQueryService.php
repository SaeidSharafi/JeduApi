<?php

declare(strict_types=1);

namespace App\Query;

use App\Data\Shop\Product\Course\CourseListRequestData;
use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\TermStatusEnum;
use App\Models\Product;
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
    public function getCourseList(CourseListRequestData $requestData): LengthAwarePaginator
    {
        $this->productableTypes = [ProductableEnum::COURSE->value]; // Narrow the productable type

        $this->availableProducts()->forListing();

        if ($requestData->filter) {
            $filter = $requestData->filter;
            if ($filter->search) {
                $this->search($filter->search);
            }
            if ($filter->categorySlug) {
                $this->inCategory($filter->categorySlug);
            }
            if ($filter->level) {
                $this->byCourseLevel(CourseDifficultyLevelEnum::from($filter->level));
            }
            if ($filter->fulfillment_type) {
                $this->byFulfillmentType($filter->fulfillment_type);
            }
            if ($filter->min_price || $filter->max_price) {
                $this->priceRange($filter->min_price, $filter->max_price);
            }
        }

        return $this
            ->sortBy($requestData->sortBy, $requestData->sortOrder)
            ->paginate($requestData->per_page);
    }

    /**
     * Get a paginated list of seminars based on filter criteria.
     */
    public function getSeminarList(ProductListRequestData $requestData): LengthAwarePaginator
    {
        return $this
            ->ofType(ProductableEnum::SEMINAR)
            ->globalSearchProducts($requestData);
    }

    /**
     * Get a paginated list of digital assets based on filter criteria.
     */
    public function getDigitalAssetList(ProductListRequestData $requestData): LengthAwarePaginator
    {
        return $this
            ->ofType(ProductableEnum::DIGITAL_ASSET)
            ->globalSearchProducts($requestData);
    }

    /**
     * Perform a global search across all available product types.
     */
    public function globalSearchProducts(ProductListRequestData $requestData): LengthAwarePaginator
    {
        // Keeps the default of all productableTypes
        $this->availableProducts()->forListing();

        if ($requestData->filter) {
            $filter = $requestData->filter;
            if ($filter->search) {
                $this->search($filter->search);
            }
            if ($filter->category_ids) {
                $this->inCategories($filter->category_ids);
            }
            if ($filter->min_price || $filter->max_price) {
                $this->priceRange($filter->min_price, $filter->max_price);
            }
            if ($filter->with_discounts) {
                $this->withDiscounts();
            }
            if ($filter->type) {
                // If a type is specified in a global search, we narrow the scope.
                $this->productableTypes = [ProductableEnum::from($filter->type)->value];
            }
        }

        return $this
            ->sortBy($requestData->sortBy, $requestData->sortOrder)
            ->paginate($requestData->per_page);
    }

    // === CORE QUERY CONFIGURATION METHODS ===

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
    public function byCourseLevel(CourseDifficultyLevelEnum $level): self
    {
        return $this->addRelationshipConstraint('productable', function ($q) use ($level) {
            $q->where('difficulty_level', $level->value);
        });
    }

    /**
     * Filter by fulfillment type. (Applies to 'productable')
     */
    public function byFulfillmentType(string $fulfillmentType): self
    {
        return $this->addRelationshipConstraint('productDeliveryOptions', function ($q) use ($fulfillmentType) {
            $q->where('fulfillment_type', $fulfillmentType);
        });
    }

    // === DIRECT QUERY FILTERS (NON-DEFERRED) ===

    public function search(?string $searchTerm): self
    {
        if (empty($searchTerm)) {
            return $this;
        }

        // Basic SQL search fallback. TODO: Replace with Typesense.
        $this->query->where(function (Builder $q) use ($searchTerm) {

            // Condition A: Search directly on the products table
            $q->whereLike('name', '%'.$searchTerm.'%')
                ->orWhereLike('short_name','%'.$searchTerm.'%')
                ->orWhereLike('short_description','%'.$searchTerm.'%');

            // Condition B: OR search within the related productable models
            // This correctly uses orWhereHasMorph, linking it to the conditions above.
            $q->orWhereHasMorph('productable', $this->productableTypes, function (Builder $sq) use ($searchTerm) {
                // No extra nested where() is needed here, as they are all OR conditions.
                $sq->whereLike('short_name', '%'.$searchTerm.'%')
                    ->orWhereLike('full_name','%'.$searchTerm.'%')
                    ->orWhereLike('description', '%'.$searchTerm.'%');
            });
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
            $this->query->join('product_prices', 'products.id', '=', 'product_prices.product_id');
            $this->appliedJoins[] = 'price_filter';
        }
    }
}
