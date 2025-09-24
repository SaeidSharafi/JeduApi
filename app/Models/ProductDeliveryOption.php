<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryMethodEnum;
use App\Enums\FulfillmentTypeEnum;
use App\Enums\GenderEnum;
use App\Enums\PublicationStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class ProductDeliveryOption extends Model
{
    use HasFactory;

    protected $fillable
        = [
            'product_id',
            'name',
            'sku',
            'fulfillment_type',
            'delivery_method',
            'price',
            'capacity',
            'status',
            'is_prepayment_available',
            'prepayment_amount',
            'details_json',
            'is_featured',
            'featured_price',
            'featured_price_start_date',
            'featured_price_end_date',
            'registration_start_date',
            'registration_end_date',
            'available_from',
            'available_to',
        ];

    protected $with
        = [
            'productDeliveryOptionDiscountPrice',
        ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'product_delivery_option_id');
    }

    public function productDeliveryOptionDiscountPrice(): HasOne
    {
        return $this->hasOne(ProductDeliveryOptionDiscountPrice::class, 'product_delivery_option_id');
    }

    public function getTeachersName(): array
    {
        return $this->teachers->map(function ($teacher) {
            $title = $teacher->gender === GenderEnum::FEMALE ? __('shop.teahcer_titles.sir') : __('shop.teahcer_titles.madam');

            return $title.' '.$teacher->first_name.' '.$teacher->last_name;
        })->toArray();
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function available($query)
    {
        // available_from and available_to are optional, if they are null, it means the product is always available
        return $query->where('status', PublicationStatusEnum::PUBLISHED)
            ->where(function (Builder $q): void {
                $q->whereNull('available_from')
                    ->orWhere('available_from', '<=', now());
            })
            ->where(function (Builder $q): void {
                $q->whereNull('available_to')
                    ->orWhere('available_to', '>=', now());
            });
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function featured($query)
    {
        return $query->where('is_featured', true)
            ->where('featured_price_start_date', '<=', now())
            ->where('featured_price_end_date', '>=', now());
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function prepaymentAvailable($query)
    {
        return $query->where('is_prepayment_available', true);
    }

    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function registrationOpen($query)
    {
        return $query->where('registration_start_date', '<=', now())
            ->where('registration_end_date', '>=', now());
    }

    protected function discountPrice(): Attribute
    {
        if ($this->relationLoaded('productDeliveryOptionDiscountPrice')) {
            return Attribute::make(
                get: fn ($value, array $attributes) => $this->productDeliveryOptionDiscountPrice?->discounted_price ?? $this->price,
            );
        }

        return Attribute::make(
            get: fn ($value, array $attributes) => $this->price,
        );
    }

    protected function casts(): array
    {
        return [
            'is_prepayment_available'   => 'boolean',
            'details_json'              => 'array',
            'is_featured'               => 'boolean',
            'status'                    => PublicationStatusEnum::class,
            'fulfillment_type'          => FulfillmentTypeEnum::class,
            'delivery_method'           => DeliveryMethodEnum::class,
            'featured_price_start_date' => 'datetime',
            'featured_price_end_date'   => 'datetime',
            'created_at'                => 'datetime',
            'updated_at'                => 'datetime',
        ];
    }
}
