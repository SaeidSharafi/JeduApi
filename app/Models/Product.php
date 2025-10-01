<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Product\RelationTypeEnum;
use App\Traits\HasCategories;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Product extends Model
{
    use HasCategories;
    use HasFactory;

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
            'status'           => \App\Enums\Content\PublicationStatusEnum::class,
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
        ];
    }
}
