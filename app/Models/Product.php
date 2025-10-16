<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\RelationTypeEnum;
use App\Enums\TermStatusEnum;
use App\Traits\HasCategories;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Scout\Searchable;

final class Product extends Model
{
    use HasCategories;
    use HasFactory;
    use Searchable;

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
        ];

    /**
     * @codeCoverageIgnore
     */
    public function toSearchableArray(): array
    {
        // Calculate static availability flags
        $publishedOptions = $this->productDeliveryOptions
            ->where('status', PublicationStatusEnum::PUBLISHED);
        $now            = now();
        $isAvailableNow = $publishedOptions->contains(function ($option) use ($now) {
            $inRegDate = (is_null($option->registration_start_date) || $now->gte($option->registration_start_date)) && (is_null($option->registration_end_date) || $now->lte($option->registration_end_date));

            $inAvailDate = (is_null($option->available_from) || $now->gte($option->available_from)) && (is_null($option->available_to) || $now->lte($option->available_to));

            return $inRegDate && $inAvailDate;
        });

        $earliestRegistrationStart = $publishedOptions->pluck('registration_start_date')->filter()->min();
        $latestRegistrationEnd     = $publishedOptions->pluck('registration_end_date')->filter()->max();
        $earliestAvailabilityStart = $publishedOptions->pluck('available_from')->filter()->min();
        $latestAvailabilityEnd     = $publishedOptions->pluck('available_to')->filter()->max();

        $hasPublishedDeliveryOption = $publishedOptions->isNotEmpty();

        $isTermActive = is_null($this->term) || $this->term->status === TermStatusEnum::ACTIVE;

        $searchableData = [
            'id'                => (string) $this->id,
            'name'              => $this->name,
            'short_name'        => $this->short_name,
            'short_description' => $this->short_description,
            'slug'              => $this->slug,

            // --- FIX: ADD THE MISSING TIMESTAMP FIELDS ---
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,

            'status'                        => $this->status->value, // e.g., 'published'
            'is_visible'                    => $this->is_visible,
            'productable_status'            => $this->productable?->status->value,
            'has_published_delivery_option' => $hasPublishedDeliveryOption,
            'is_term_active'                => $isTermActive,

            'is_available_now' => $isAvailableNow,

            'earliest_registration_start_ts' => $earliestRegistrationStart?->timestamp,
            'latest_registration_end_ts'     => $latestRegistrationEnd?->timestamp,
            'earliest_availability_start_ts' => $earliestAvailabilityStart?->timestamp,
            'latest_availability_end_ts'     => $latestAvailabilityEnd?->timestamp,

            'price'             => $this->productPrice?->min_price    ?? ($this->price_data_cache['min_price'] ?? 0),
            'has_discount'      => $this->productPrice?->has_discount ?? ($this->price_data_cache['has_discount'] ?? false),
            'category_ids'      => $this->categories->pluck('id')->all(),
            'fulfillment_types' => $this->productDeliveryOptions->pluck('fulfillment_type')->unique()->values()->all(),
            'category_slugs'    => $this->categories->pluck('slug')->all(),
            'productable_type'  => $this->productable_type,
            'difficulty_level'  => $this->productable->difficulty_level?->value,

            'productable_full_name'   => $this->productable?->full_name,
            'productable_short_name'  => $this->productable?->short_name,
            'productable_description' => $this->productable?->description,
        ];

        return $searchableData;
    }

    /**
     * @codeCoverageIgnore
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
        return $this->status === PublicationStatusEnum::PUBLISHED
            && $this->is_visible
            && (! $this->term || $this->term->status === TermStatusEnum::ACTIVE)
            && $this->productable?->status === PublicationStatusEnum::PUBLISHED
            && $this->productDeliveryOptions()
                ->where('status', PublicationStatusEnum::PUBLISHED)
                ->exists();
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function productable(): MorphTo
    {
        return $this->morphTo();
    }

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

    protected function casts(): array
    {
        return [
            'is_visible'       => 'boolean',
            'is_featured'      => 'boolean',
            'price_data_cache' => 'array',
            'details_json'     => 'array',
            'status'           => PublicationStatusEnum::class,
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
        ];
    }
}
