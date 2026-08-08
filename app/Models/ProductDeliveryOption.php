<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductDeliveryOption extends Model
{
    /** @use HasFactory<\Database\Factories\ProductDeliveryOptionFactory> */
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
            'enrolled_count',
            'reserved_count',
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
            'access_days',
        ];

    protected $with
        = [
            'productDeliveryOptionDiscountPrice',
        ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<Teacher, $this>
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'product_delivery_option_id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_delivery_option_id');
    }

    /**
     * @return HasOne<ProductDeliveryOptionDiscountPrice, $this>
     */
    public function productDeliveryOptionDiscountPrice(): HasOne
    {
        return $this->hasOne(ProductDeliveryOptionDiscountPrice::class, 'product_delivery_option_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($model): void {
            $model->uuid = (string) Str::uuid7();
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function available(Builder $query): Builder
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
            })
            ->where(function (Builder $q): void {
                $q->where(function (Builder $sq): void {
                    // Both dates are null (always open)
                    $sq->whereNull('registration_start_date')
                        ->whereNull('registration_end_date');
                })
                    ->orWhere(function (Builder $sq): void {
                        // Both dates exist and current time is within range
                        $sq->whereNotNull('registration_start_date')
                            ->whereNotNull('registration_end_date')
                            ->where('registration_start_date', '<=', now())
                            ->where('registration_end_date', '>=', now());
                    })
                    ->orWhere(function (Builder $sq): void {
                        // Only start date exists and we're past it
                        $sq->whereNotNull('registration_start_date')
                            ->whereNull('registration_end_date')
                            ->where('registration_start_date', '<=', now());
                    })
                    ->orWhere(function (Builder $sq): void {
                        // Only end date exists and we're before it
                        $sq->whereNull('registration_start_date')
                            ->whereNotNull('registration_end_date')
                            ->where('registration_end_date', '>=', now());
                    });
            });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function availableWithCapacity(Builder $query): Builder
    {
        return $query->available() // Use the query builder method, not scope method directly
            ->where(function (Builder $q): void {
                $q->whereNull('capacity')
                    // enrolled_count + reserved_count = committed seats (sold OR held
                    // by an unpaid order). Capacity must exceed both.
                    ->orWhereColumn('capacity', '>', DB::raw('(enrolled_count + reserved_count)')); // @phpstan-ignore argument.type
            });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function withCapacityInfo(Builder $query): Builder
    {
        // enrolled_count is now a database column, no need for withCount
        // This scope is kept for backward compatibility but does nothing now
        return $query;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function featured(Builder $query): Builder
    {
        return $query->where('is_featured', true)
            ->where('featured_price_start_date', '<=', now())
            ->where('featured_price_end_date', '>=', now());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function prepaymentAvailable(Builder $query): Builder
    {
        return $query->where('is_prepayment_available', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function registrationOpen(Builder $query): Builder
    {
        return $query->where('registration_start_date', '<=', now())
            ->where('registration_end_date', '>=', now());
    }

    /**
     * @return Attribute<int, int>
     */
    protected function discountPrice(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                $discountRecord = $this->productDeliveryOptionDiscountPrice;

                if (! $discountRecord) {
                    return $this->price;
                }
                $now    = now();
                $starts = $discountRecord->starts_at;
                $ends   = $discountRecord->ends_at;

                $isAfterStart = is_null($starts) || $now->greaterThanOrEqualTo($starts);
                $isBeforeEnd  = is_null($ends)   || $now->lessThanOrEqualTo($ends);

                return ($isAfterStart && $isBeforeEnd) ? $discountRecord->discounted_price : $this->price;
            }
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function imsCourseCode(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $code = $this->details_json['ims_course_code'] ?? null;

                return is_string($code) ? $code : '';
            },
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
            'available_from'            => 'datetime',
            'available_to'              => 'datetime',
            'registration_start_date'   => 'datetime',
            'registration_end_date'     => 'datetime',
            'created_at'                => 'datetime',
            'updated_at'                => 'datetime',
        ];
    }
}
