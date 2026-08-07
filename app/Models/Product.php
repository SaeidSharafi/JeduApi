<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\AvailabilityStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Enums\Product\RelationTypeEnum;
use App\Enums\TermStatusEnum;
use App\Traits\HasCategories;
use App\Traits\HasProductListingPresets;
use BackedEnum;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

final class Product extends Model
{
    use HasCategories;
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;
    use HasProductListingPresets;
    use Searchable {
        search as scoutSearch;
    }

    private const int OPEN_END_TIMESTAMP = 4102444800;

    protected $fillable
        = [
            'vendor_id',
            'productable_id',
            'productable_type',
            'term_id',
            'status',
            'is_visible',
            'short_description',
            'short_name',
            'name',
            'slug',
            'is_featured',
            'price_data_cache',
            'details_json',
            'event_start_at',
            'event_ended_at',
            'has_published_delivery_option',
            'productable_status',
            'is_term_active',
            'earliest_registration_start',
            'latest_registration_end',
            'earliest_availability_start',
            'latest_availability_end',
            'near_capacity',
            'max_capacity_utilization',
        ];

    /**
     * @codeCoverageIgnore
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $publishedOptions = $this->productDeliveryOptions
            ->where('status', PublicationStatusEnum::PUBLISHED);

        $searchableData = [
            'id'                => (string) $this->id,
            'name'              => $this->name,
            'short_name'        => $this->short_name,
            'short_description' => $this->short_description,
            'slug'              => $this->slug,

            'created_at' => (int) $this->created_at->timestamp,
            'updated_at' => (int) $this->updated_at->timestamp,

            'status'                        => $this->status->value,
            'is_visible'                    => (bool) $this->is_visible,
            'productable_status'            => (string) $this->productable_status,
            'has_published_delivery_option' => (bool) $this->has_published_delivery_option,
            'is_term_active'                => (bool) $this->is_term_active,
            'near_capacity'                 => (bool) $this->near_capacity,
            'max_capacity_utilization'      => (float) $this->max_capacity_utilization,

            'earliest_registration_start_ts' => $this->earliest_registration_start?->startOfDay()->timestamp ?? 0,
            'latest_registration_end_ts'     => $this->latest_registration_end?->endOfDay()->timestamp       ?? self::OPEN_END_TIMESTAMP,
            'earliest_availability_start_ts' => $this->earliest_availability_start?->startOfDay()->timestamp ?? 0,
            'latest_availability_end_ts'     => $this->latest_availability_end?->endOfDay()->timestamp       ?? self::OPEN_END_TIMESTAMP,

            'earliest_event_start_ts' => $this->event_start_at->timestamp             ?? 0,
            'latest_event_ended_ts'   => $this->event_ended_at?->endOfDay()->timestamp ?? self::OPEN_END_TIMESTAMP,

            'price'             => (int) ($this->productPrice->min_price ?? ($this->price_data_cache['min_price'] ?? 0)),
            'has_discount'      => (bool) ($this->productPrice->has_discount ?? ($this->price_data_cache['has_discount'] ?? false)),
            'category_ids'      => $this->categories->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
            'fulfillment_types' => $publishedOptions
                ->pluck('fulfillment_type')
                ->map(static fn (mixed $type): string => $type instanceof BackedEnum ? (string) $type->value : (string) $type)
                ->unique()
                ->values()
                ->all(),
            'category_slugs'   => $this->categories->pluck('slug')->map(static fn (mixed $slug): string => (string) $slug)->all(),
            'productable_type' => (string) $this->productable_type,

            'productable_full_name'   => $this->productable?->full_name,
            'productable_short_name'  => $this->productable?->short_name,
            'productable_description' => $this->productable?->description,
            'difficulty_level'        => $this->productable?->difficulty_level?->value,
        ];

        return $searchableData;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param Collection<int, self> $models
     *
     * @return Collection<int, self>
     */
    public function makeSearchableUsing(Collection $models): Collection
    {
        return $models->loadMissing([
            'productable',
            'categories:id,slug',
            'productPrice',
            'productDeliveryOptions',
            'term:id,status',
        ]);
    }

    public function shouldBeSearchable(): bool
    {
        if (config('products.availability.use_denormalized')) {
            return $this->status === PublicationStatusEnum::PUBLISHED
                && (bool) $this->is_visible
                && $this->productable_status === PublicationStatusEnum::PUBLISHED->value
                && (bool) $this->has_published_delivery_option
                && (bool) $this->is_term_active;
        }

        return $this->status === PublicationStatusEnum::PUBLISHED
            && $this->is_visible
            && (! $this->term || $this->term->status === TermStatusEnum::ACTIVE)
            && $this->productable?->status === PublicationStatusEnum::PUBLISHED
            && $this->productDeliveryOptions()
                ->where('status', PublicationStatusEnum::PUBLISHED)
                ->exists();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return MorphTo<Course|Seminar|DigitalAsset, $this>
     */
    public function productable(): MorphTo
    {
        return $this->morphTo(); // @phpstan-ignore return.type (larastan types morphTo() as MorphTo<Model>)
    }

    /**
     * @return MorphTo<Course|Seminar|DigitalAsset, $this>
     */
    public function productableWithAllRelations(): MorphTo
    {
        return $this->productable()
            ->withProductableMedia()
            ->withProductableCategories()
            ->withProductableAssets();
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return HasMany<ProductDeliveryOption, $this>
     */
    public function productDeliveryOptions(): HasMany
    {
        return $this->hasMany(ProductDeliveryOption::class);
    }

    /**
     * @return HasManyThrough<OrderItem, ProductDeliveryOption, $this>
     */
    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, ProductDeliveryOption::class);
    }

    /**
     * @return HasOne<ProductPrice, $this>
     */
    public function productPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class);
    }

    /**
     * Get all related products regardless of relation type.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'related_products',
            'product_id',
            'related_product_id'
        )->withPivot('relation_type')
            ->withTimestamps();
    }

    /**
     * Get products marked as "related" (similar/alternative products).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function relatedProductsOfType(): BelongsToMany
    {
        return $this->relatedProducts()
            ->wherePivot('relation_type', RelationTypeEnum::RELATED->value);
    }

    /**
     * Get products marked as "cross-sell" (frequently bought together).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function crossSellProducts(): BelongsToMany
    {
        return $this->relatedProducts()
            ->wherePivot('relation_type', RelationTypeEnum::CROSS_SELL->value);
    }

    /**
     * Get products marked as "upsell" (premium alternatives).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function upsellProducts(): BelongsToMany
    {
        return $this->relatedProducts()
            ->wherePivot('relation_type', RelationTypeEnum::UPSELL->value);
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function eventEnded(Builder $query): Builder
    {
        return $query->whereNotNull('event_ended_at')
            ->where('event_ended_at', '<', today()->startOfDay());
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function eventNotStarted(Builder $query): Builder
    {
        return $query->whereNotNull('event_start_at')
            ->where('event_start_at', '>', today()->startOfDay());
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function eventOngoing(Builder $query): Builder
    {
        return $query->whereNotNull('event_start_at')
            ->where('event_start_at', '<=', today()->startOfDay())
            ->whereNotNull('event_ended_at')
            ->where('event_ended_at', '>=', today()->startOfDay());
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function eventNotEnded(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('event_ended_at')
                ->orWhere('event_ended_at', '>=', today()->startOfDay());
        });
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function publishedAndVisible(Builder $query): Builder
    {
        return $query->where('products.status', PublicationStatusEnum::PUBLISHED)
            ->where('products.is_visible', true);
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function hasPublishedDeliveryOption(Builder $query): Builder
    {
        if (config('products.availability.use_denormalized')) {
            return $query->where('products.has_published_delivery_option', true);
        }

        return $query->whereHas('productDeliveryOptions', fn (Builder $query): Builder => $query
            ->where('status', PublicationStatusEnum::PUBLISHED));
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function publishedProductable(Builder $query): Builder
    {
        if (config('products.availability.use_denormalized')) {
            return $query->where('products.productable_status', PublicationStatusEnum::PUBLISHED);
        }

        return $query->whereHasMorph('productable', ProductableEnum::getAllValues(), fn (Builder $query): Builder => $query
            ->where('status', PublicationStatusEnum::PUBLISHED));
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function activeTerm(Builder $query): Builder
    {
        if (config('products.availability.use_denormalized')) {
            return $query->where('products.is_term_active', true);
        }

        return $query->where(function (Builder $query): void {
            $query->whereNull('term_id')
                ->orWhereHas('term', fn (Builder $termQuery): Builder => $termQuery
                    ->where('status', TermStatusEnum::ACTIVE));
        });
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function availabilityStatus(Builder $query, AvailabilityStatusEnum $status): Builder
    {
        $today = today()->startOfDay();

        return $query->where(function (Builder $query) use ($status, $today): void {
            match ($status) {
                AvailabilityStatusEnum::PAST => $query
                    ->where('products.event_ended_at', '<', $today)
                    ->orWhere(function (Builder $fallback) use ($today): void {
                        $fallback->whereNull('products.event_start_at')
                            ->whereNull('products.event_ended_at')
                            ->whereHas('productDeliveryOptions', fn (Builder $optionQuery): Builder => $optionQuery
                                ->where('available_to', '<', $today));
                    }),
                AvailabilityStatusEnum::UPCOMING => $query
                    ->where('products.event_start_at', '>', $today)
                    ->orWhere(function (Builder $fallback) use ($today): void {
                        $fallback->whereNull('products.event_start_at')
                            ->whereNull('products.event_ended_at')
                            ->whereHas('productDeliveryOptions', fn (Builder $optionQuery): Builder => $optionQuery
                                ->where('available_from', '>', $today));
                    }),
                AvailabilityStatusEnum::ONGOING => $query
                    ->where(function (Builder $eventQuery) use ($today): void {
                        $eventQuery->whereNotNull('products.event_start_at')
                            ->where('products.event_start_at', '<=', $today)
                            ->whereNotNull('products.event_ended_at')
                            ->where('products.event_ended_at', '>=', $today);
                    })->orWhere(function (Builder $fallback) use ($today): void {
                        $fallback->whereNull('products.event_start_at')
                            ->whereNull('products.event_ended_at')
                            ->whereHas('productDeliveryOptions', function (Builder $optionQuery) use ($today): void {
                                $optionQuery->where('available_from', '<=', $today)
                                    ->where(function (Builder $endQuery) use ($today): void {
                                        $endQuery->whereNull('available_to')
                                            ->orWhere('available_to', '>=', $today);
                                    });
                            });
                    }),
            };
        });
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function sortByCapacityUtilization(Builder $query, float $threshold = 0.8): Builder
    {
        $threshold = max(0.0, min(1.0, $threshold));

        if (config('products.availability.use_denormalized')) {
            return $query
                ->orderByRaw('CASE WHEN products.max_capacity_utilization >= ? THEN 1 ELSE 0 END DESC', [$threshold])
                ->orderByDesc('products.max_capacity_utilization');
        }

        $publishedStatus = PublicationStatusEnum::PUBLISHED->value;

        return $query
            ->select('products.*')
            ->leftJoinLateral(
                DB::table('product_delivery_options AS pdo_lat')
                    ->selectRaw('COALESCE(MAX((pdo_lat.enrolled_count * 1.0) / NULLIF(pdo_lat.capacity, 0)), 0) AS max_ratio')
                    ->selectRaw('COALESCE(MAX(CASE WHEN ((pdo_lat.enrolled_count * 1.0) / NULLIF(pdo_lat.capacity, 0)) >= ? THEN 1 ELSE 0 END), 0) AS near_capacity_flag', [$threshold])
                    ->whereColumn('pdo_lat.product_id', 'products.id')
                    ->where('pdo_lat.status', $publishedStatus)
                    ->whereNotNull('pdo_lat.capacity')
                    ->where('pdo_lat.capacity', '>', 0),
                'pdo_cap_stats'
            )
            ->orderByRaw('pdo_cap_stats.near_capacity_flag DESC')
            ->orderByRaw('pdo_cap_stats.max_ratio DESC');
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function ofType(Builder $query, ProductableEnum $type): Builder
    {
        return $query->where('products.productable_type', $type->value);
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function inCategory(Builder $query, int $categoryId): Builder
    {
        return $query->whereHas('categories', fn (Builder $categoryQuery): Builder => $categoryQuery
            ->where('categories.id', $categoryId));
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    #[Scope]
    protected function search(Builder $query, ?string $searchTerm): Builder
    {
        if (blank($searchTerm)) {
            return $query;
        }

        return $query->where(function (Builder $searchQuery) use ($searchTerm): void {
            $searchQuery
                ->withPgroonga()
                ->fullTextSearch(['name', 'short_name', 'short_description', 'slug'], $searchTerm);

            foreach (ProductableEnum::getAllValues() as $type) {
                $searchQuery->orWhereHasMorph('productable', [$type], function (Builder $productableQuery) use ($searchTerm, $type): void {
                    $columns = ['full_name', 'short_name', 'description', 'slug'];

                    if (in_array($type, [ProductableEnum::SEMINAR->value, ProductableEnum::DIGITAL_ASSET->value], true)) {
                        $columns[] = 'keywords';
                    }

                    $productableQuery->withPgroonga()->fullTextSearch($columns, $searchTerm);
                });
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_visible'                    => 'boolean',
            'is_featured'                   => 'boolean',
            'price_data_cache'              => 'array',
            'details_json'                  => 'array',
            'event_start_at'                => 'datetime',
            'event_ended_at'                => 'datetime',
            'has_published_delivery_option' => 'boolean',
            'is_term_active'                => 'boolean',
            'earliest_registration_start'   => 'date',
            'latest_registration_end'       => 'date',
            'earliest_availability_start'   => 'date',
            'latest_availability_end'       => 'date',
            'near_capacity'                 => 'boolean',
            'max_capacity_utilization'      => 'decimal:2',
            'status'                        => PublicationStatusEnum::class,
            'created_at'                    => 'datetime',
            'updated_at'                    => 'datetime',
        ];
    }
}
