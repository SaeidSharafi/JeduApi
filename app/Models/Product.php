<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasCategories;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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

    protected function casts(): array
    {
        return [
            'is_visible'       => 'boolean',
            'is_featured'      => 'boolean',
            'price_data_cache' => 'array',
            'details_json'     => 'array',
            'status'           => \App\Enums\PublicationStatusEnum::class,
            'created_at'       => 'datetime',
            'updated_at'       => 'datetime',
        ];
    }

    #[Scope]
    protected function active($query)
    {
        return $query->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED)
            ->where('is_visible', true)
            ->whereHas('productable', function ($q): void {
                $q->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED);
            })
            ->whereHas('productDeliveryOptions', function ($q): void {
                $q->available();
            });
    }

    #[Scope]
    protected function activeWithRelations($query)
    {
        return $query
            ->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED)
            ->where('is_visible', true)
            ->withWhereHas('productDeliveryOptions', function ($q): void {
                $q->available()
                    ->with('productDeliveryOptionDiscountPrice');
            })
            ->withWhereHas('productable', function ($q): void {
                $q->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED)
                    ->withProductableMedia()
                    ->withProductableCategories()
                    ->withProductableAssets();
            })
            ->with('vendor');
    }

    #[Scope]
    protected function activeWithData(Builder $query): Builder
    {
        return $query
            ->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED)
            ->where('is_visible', true)
            ->withWhereHas(
                'productDeliveryOptions', function ($q): void {
                    $q->with(['teachers', 'productDeliveryOptionDiscountPrice'])
                        ->available();
                })
            ->withWhereHas('productable', function ($q): void {
                $q->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED);
            })
            ->with('vendor');
    }

    #[Scope]
    protected function activeWithPriceAndMedia($query)
    {
        return $query
            ->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED)
            ->where('is_visible', true)
            ->withWhereHas(
                'productDeliveryOptions', function ($q): void {
                    $q->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED)
                        ->with('productDeliveryOptionDiscountPrice');
                })
            ->withWhereHas('productable', function ($q): void {
                $q->where('status', \App\Enums\PublicationStatusEnum::PUBLISHED)
                    ->withProductableMedia();
            })
            ->with('vendor');
    }
}
