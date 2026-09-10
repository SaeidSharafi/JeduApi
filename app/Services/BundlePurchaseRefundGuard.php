<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Exceptions\RefundValidationException;
use App\Models\BundlePurchase;
use App\Models\OrderItem;
use App\Models\Refund;

/**
 * Shared eligibility rules for refunding a Bundle Purchase as one indivisible
 * commercial unit, used by both the Bundle refund action and the full-order
 * refund action so their component rules cannot drift apart.
 */
final class BundlePurchaseRefundGuard
{
    /** @throws RefundValidationException */
    public function assertRefundable(BundlePurchase $bundlePurchase): void
    {
        $components = $bundlePurchase->components;
        if ($components->isEmpty()) {
            throw new RefundValidationException(__('messages.order.refund.bundle_purchase_not_refundable'));
        }

        foreach ($components as $component) {
            if ($component->status === OrderItemStatusEnum::REFUNDED) {
                throw new RefundValidationException(__('messages.order.refund.already_refunded'));
            }
            if ($component->status === OrderItemStatusEnum::CANCELLED) {
                throw new RefundValidationException(__('messages.order.refund.bundle_purchase_not_refundable'));
            }
            if ($this->hasActiveRefund($component)) {
                throw new RefundValidationException(__('messages.order.refund.refund_request_exists'));
            }
        }
    }

    /**
     * Whether every component is already closed or covered by an active refund
     * request, so the Bundle Purchase is not discovered again.
     */
    public function isFullyHandled(BundlePurchase $bundlePurchase): bool
    {
        $components = $bundlePurchase->components;

        return $components->isNotEmpty() && $components->every(
            fn (OrderItem $component): bool => $this->isClosed($component) || $this->hasActiveRefund($component)
        );
    }

    private function isClosed(OrderItem $item): bool
    {
        return in_array($item->status, [OrderItemStatusEnum::REFUNDED, OrderItemStatusEnum::CANCELLED], true);
    }

    private function hasActiveRefund(OrderItem $item): bool
    {
        return $item->refunds->contains(
            fn (Refund $refund): bool => $refund->status !== RefundStatusEnum::FAILED
        );
    }
}
