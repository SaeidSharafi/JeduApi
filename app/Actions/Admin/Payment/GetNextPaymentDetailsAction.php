<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Data\Admin\Payment\NextPaymentDetailsData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Payment\NextPaymentTypeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Exceptions\Payment\OrderFullyPaidException;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

final class GetNextPaymentDetailsAction
{
    /**
     * Calculates and describes the next logical payment for an order.
     * This is a READ-ONLY action used to inform the admin.
     *
     * @throws OrderFullyPaidException
     */
    public function handle(Order $order): NextPaymentDetailsData
    {
        // Eager load everything we need upfront for efficiency.
        $order->load('payments', 'items');

        // --- EDGE CASE 1: FREE ORDER ---
        if ($order->grand_total <= 0) {
            return new NextPaymentDetailsData(
                amount_due: 0,
                payment_type: NextPaymentTypeEnum::NONE,
                summary_description: __('messages.order.no_payment_required'),
                line_item_details: []
            );
        }

        // --- EDGE CASE 2: FULLY PAID ORDER ---
        if ($order->balance_due <= 0) {
            throw new OrderFullyPaidException($order->increment_id);
        }

        // --- GATHER AND PARTITION ITEMS ---
        // This is the key to building detailed descriptions.
        $fullPaymentItems = $order->items->where('payment_type', OrderItemPaymentTypeEnum::FULL_PAYMENT);
        $prePaymentItems  = $order->items->where('payment_type', OrderItemPaymentTypeEnum::PRE_PAYMENT);

        // --- DETERMINE PAYMENT STAGE ---
        $hasCompletedPayments = $order->payments->where('status', PaymentStatusEnum::COMPLETED)->isNotEmpty();

        if (! $hasCompletedPayments) {
            // --- STAGE 1: INITIAL PAYMENT ---
            return $this->buildInitialPaymentDetails($fullPaymentItems, $prePaymentItems);
        }

        // --- STAGE 2: FINAL BALANCE PAYMENT ---
        return $this->buildFinalBalancePaymentDetails($order, $prePaymentItems);

    }

    /**
     * Builds the details for the very first payment on an order.
     */
    /**
     * @param  Collection<int, OrderItem>  $fullPaymentItems
     * @param  Collection<int, OrderItem>  $prePaymentItems
     */
    private function buildInitialPaymentDetails(
        Collection $fullPaymentItems,
        Collection $prePaymentItems
    ): NextPaymentDetailsData {
        $amount      = 0.0;
        $lineDetails = [];

        // Process full payment items
        if ($fullPaymentItems->isNotEmpty()) {
            $amount += $fullPaymentItems->sum('total');
            $lineDetails[] = [
                'type' => [
                    'value' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
                    'label' => OrderItemPaymentTypeEnum::FULL_PAYMENT->translate(),
                ],
                'items' => $this->formatItemList($fullPaymentItems),
            ];
        }

        // Process pre-payment items
        if ($prePaymentItems->isNotEmpty()) {
            $amount += $prePaymentItems->sum('total');
            $lineDetails[] = [
                'type' => [
                    'value' => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
                    'label' => OrderItemPaymentTypeEnum::PRE_PAYMENT->translate(),
                ],
                'items' => $this->formatItemList($prePaymentItems),
            ];
        }

        return new NextPaymentDetailsData(
            amount_due: (int) $amount,
            payment_type: NextPaymentTypeEnum::INITIAL_PAYMENT,
            summary_description: $this->generateInitialSummary($fullPaymentItems, $prePaymentItems),
            line_item_details: $lineDetails
        );
    }

    /**
     * Builds the details for the final balance payment.
     */
    /**
     * @param  Collection<int, OrderItem>  $prePaymentItems
     */
    private function buildFinalBalancePaymentDetails(
        Order $order,
        Collection $prePaymentItems
    ): NextPaymentDetailsData {
        return new NextPaymentDetailsData(
            amount_due: (int) $order->balance_due,
            payment_type: NextPaymentTypeEnum::FINAL_BALANCE,
            summary_description: __('messages.order.final_balance_payment_required'),
            line_item_details: [
                [
                    'type' => [ // Add this block for consistency
                        'value' => 'final_balance',
                        'label' => __('messages.order.final_balance_payment'),
                    ],
                    'items' => $this->formatItemList($prePaymentItems),
                ],
            ]
        );
    }

    /**
     * Generates a clear summary string for the initial payment scenario.
     */
    /**
     * @param  Collection<int, OrderItem>  $fullPaymentItems
     * @param  Collection<int, OrderItem>  $prePaymentItems
     */
    private function generateInitialSummary(Collection $fullPaymentItems, Collection $prePaymentItems): string
    {
        $hasFull = $fullPaymentItems->isNotEmpty();
        $hasPre  = $prePaymentItems->isNotEmpty();

        if ($hasFull && ! $hasPre) {
            // 'This is a full and final payment that will settle the order completely.';
            return __('messages.order.initial_payment_full');
        }
        if (! $hasFull && $hasPre) {
            // 'This is a partial payment covering only the pre-payment fees. A final balance payment will be required later.';
            return __('messages.order.initial_payment_partial');
        }

        // 'This is an initial mixed payment. It covers the full cost of some items and only the pre-payment fee for others. A final balance payment will be required later.';
        return __('messages.order.initial_payment_mixed');

    }

    /**
     * Helper to format a collection of order items into a simple array of names.
     *
     * @param  Collection<int, OrderItem>  $items
     * @return array<int, string>
     */
    private function formatItemList(Collection $items): array
    {
        return $items->map(fn (OrderItem $item) => $item->name)->values()->all();
    }
}
