<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BundlePurchaseStatusEnum;
use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    /**
     * Derived aggregate status of the whole Bundle Purchase.
     *
     * Deterministic function of the owning Order's payment state and every
     * component OrderItem's status, its Enrollment's lifecycle status, the
     * Enrollment's aggregate provisioning health, and — once refunded — the
     * per-component revocation state. `revocation_pending` is returned while at
     * least one refunded component's provider access is not yet revoked;
     * `refunded` requires every required revocation to have succeeded. A
     * suspended Enrollment keeps its seat but does not grant active access, so
     * it is counted as non-active.
     *
     * @return Attribute<BundlePurchaseStatusEnum, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: fn (): BundlePurchaseStatusEnum => $this->deriveAggregateStatus());
    }

    protected function casts(): array
    {
        return [
            'base_value'      => 'integer', 'selling_price' => 'integer', 'composition_version' => 'integer',
            'checkout_status' => OrderStatusEnum::class, 'product_data_snapshot_json' => 'array',
        ];
    }

    private function deriveAggregateStatus(): BundlePurchaseStatusEnum
    {
        $this->loadMissing(['order.payments', 'components.enrollment']);
        $components = $this->components;
        $order      = $this->order;

        if ($components->isEmpty()) {
            return BundlePurchaseStatusEnum::PROVISIONING;
        }

        $hasCompletedPayment = $order?->payments->contains(
            fn ($payment): bool => $payment->status === PaymentStatusEnum::COMPLETED
        ) ?? false;
        $orderCancelled = $order?->status === OrderStatusEnum::CANCELLED;

        $itemStatuses      = $components->pluck('status');
        $allItemsCancelled = $itemStatuses->every(fn ($status): bool => $status === OrderItemStatusEnum::CANCELLED);
        $enrollments       = $components->pluck('enrollment')->filter();
        $allEnrolled       = $components->isNotEmpty()
            && $components->count() === $enrollments->count()
            && $enrollments->every(fn ($enrollment): bool => $enrollment->enrollment_status === EnrollmentStatusEnum::CANCELLED);

        // Nothing has been paid yet: the purchase is either awaiting payment or
        // was cancelled while the order was still pending/unpaid.
        if (! $hasCompletedPayment) {
            if ($orderCancelled || $allItemsCancelled || $allEnrolled) {
                return BundlePurchaseStatusEnum::CANCELLED;
            }

            return BundlePurchaseStatusEnum::PENDING_PAYMENT;
        }

        if ($itemStatuses->every(fn ($status): bool => $status === OrderItemStatusEnum::REFUNDED)) {
            return $this->allRevocationsComplete($components)
                ? BundlePurchaseStatusEnum::REFUNDED
                : BundlePurchaseStatusEnum::REVOCATION_PENDING;
        }

        // Payment received: every component Order Item is COMPLETED and each
        // Enrollment carries its own lifecycle + provisioning health.
        $ok       = 0;
        $pending  = 0;
        $failed   = 0;
        $inactive = 0;

        foreach ($components as $component) {
            if ($component->status    === OrderItemStatusEnum::CANCELLED
                || $component->status === OrderItemStatusEnum::REFUNDED
            ) {
                $inactive++;

                continue;
            }

            $enrollment = $component->enrollment;
            if (! $enrollment
                || $component->status             === OrderItemStatusEnum::PENDING
                || $enrollment->enrollment_status === EnrollmentStatusEnum::AWAITING_PAYMENT
            ) {
                $pending++;

                continue;
            }

            // CANCELLED and SUSPENDED Enrollments do not grant active access:
            // they count as non-active rather than inflating the aggregate to
            // ACTIVE. (The dedicated revocation_pending mapping arrives with
            // the Bundle refund/revocation flow.)
            if ($enrollment->enrollment_status    === EnrollmentStatusEnum::CANCELLED
                || $enrollment->enrollment_status === EnrollmentStatusEnum::SUSPENDED
            ) {
                $failed++;

                continue;
            }

            $provisioning = $enrollment->provisioning_status;
            if ($provisioning    === ProvisioningStatusEnum::DEGRADED
                || $provisioning === ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED
            ) {
                $failed++;

                continue;
            }

            if ($provisioning === ProvisioningStatusEnum::HEALTHY) {
                $ok++;

                continue;
            }

            $pending++;
        }

        if ($inactive === $components->count()) {
            if ($allItemsCancelled) {
                return BundlePurchaseStatusEnum::CANCELLED;
            }

            return $this->allRevocationsComplete($components)
                ? BundlePurchaseStatusEnum::REFUNDED
                : BundlePurchaseStatusEnum::REVOCATION_PENDING;
        }

        if ($pending > 0) {
            return BundlePurchaseStatusEnum::PROVISIONING;
        }

        if ($failed > 0 && $ok > 0) {
            return BundlePurchaseStatusEnum::PARTIALLY_FAILED;
        }

        if ($failed > 0) {
            return BundlePurchaseStatusEnum::FAILED;
        }

        if ($ok > 0) {
            return BundlePurchaseStatusEnum::ACTIVE;
        }

        return BundlePurchaseStatusEnum::PROVISIONING;
    }

    /**
     * Whether every refunded component's external provider access is revoked.
     *
     * A component without an Enrollment has nothing to revoke; a component
     * cancelled without a revocation record (ordinary refunds) is complete. Any
     * pending, failed, or manual revocation keeps the Purchase in
     * `revocation_pending`, which also keeps Purchase Eligibility blocked.
     *
     * @param  Collection<int, OrderItem>  $components
     */
    private function allRevocationsComplete(Collection $components): bool
    {
        foreach ($components as $component) {
            $enrollment = $component->enrollment;
            if (! $enrollment) {
                continue;
            }

            if ($enrollment->revocation_status === EnrollmentRevocationStatusEnum::REVOKED) {
                continue;
            }

            if ($enrollment->revocation_status    === null
                && $enrollment->enrollment_status === EnrollmentStatusEnum::CANCELLED
            ) {
                continue;
            }

            return false;
        }

        return true;
    }
}
