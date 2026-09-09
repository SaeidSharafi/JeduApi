<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BundlePurchase extends Model
{
    protected $fillable = [
        'order_id', 'product_delivery_option_id', 'bundle_name', 'product_name', 'name', 'sku',
        'base_value', 'selling_price', 'composition_version', 'checkout_status', 'product_data_snapshot_json',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ProductDeliveryOption, $this> */
    public function productDeliveryOption(): BelongsTo
    {
        return $this->belongsTo(ProductDeliveryOption::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('product_delivery_option_id');
    }

    /** @return Attribute<OrderStatusEnum, never> */
    protected function status(): Attribute
    {
        return Attribute::make(get: function (): OrderStatusEnum {
            $this->loadMissing('components.enrollment');
            $statuses     = $this->components->pluck('status');
            $enrollments  = $this->components->pluck('enrollment.enrollment_status');
            $allCancelled = $enrollments->isNotEmpty() && $enrollments->every(fn ($value): bool => $value === EnrollmentStatusEnum::CANCELLED);
            if ($allCancelled && ! $statuses->contains(OrderItemStatusEnum::REFUNDED)) {
                return OrderStatusEnum::CANCELLED;
            }
            if ($statuses->contains(OrderItemStatusEnum::REFUNDED) && ! $allCancelled) {
                return OrderStatusEnum::PROCESSING;
            }
            if ($enrollments->contains(EnrollmentStatusEnum::SUSPENDED)) {
                return OrderStatusEnum::PROCESSING;
            }
            foreach ([
                OrderItemStatusEnum::PENDING->value   => OrderStatusEnum::PENDING,
                OrderItemStatusEnum::COMPLETED->value => OrderStatusEnum::COMPLETED,
                OrderItemStatusEnum::CANCELLED->value => OrderStatusEnum::CANCELLED,
                OrderItemStatusEnum::REFUNDED->value  => OrderStatusEnum::REFUNDED,
            ] as $itemStatus => $status) {
                if ($statuses->isNotEmpty() && $statuses->every(fn (OrderItemStatusEnum $value): bool => $value->value === $itemStatus)) {
                    return $status;
                }
            }

            return OrderStatusEnum::PROCESSING;
        });
    }

    protected function casts(): array
    {
        return [
            'base_value'      => 'integer', 'selling_price' => 'integer', 'composition_version' => 'integer',
            'checkout_status' => OrderStatusEnum::class, 'product_data_snapshot_json' => 'array',
        ];
    }
}
