<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Data\Admin\Refund\RefundOrderData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Events\RefundCompletedEvent;
use App\Exceptions\RefundGatewayException;
use App\Exceptions\RefundValidationException;
use App\Models\BundlePurchase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\BundlePurchaseRefundGuard;
use App\Services\MixedOrderRefundCalculator;
use App\Services\OrderStatusService;
use App\Services\Payment\Refund\RefundProcessorFactory;
use App\Services\Provisioning\EnrollmentRevocationService;
use App\Services\RefundPaymentGuard;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Refunds a complete mixed Order atomically.
 *
 * Each standalone Order Item is one commercial unit and each Bundle Purchase is
 * one indivisible unit; internal Bundle Component Order Items never count as
 * additional units. Every unit is discovered and validated before any gateway
 * call or local financial mutation, exactly one combined gateway refund is
 * issued for the final calculated amount, and only after financial success do
 * the affected component Enrollments become revocation-pending.
 */
final class RefundOrderAction
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly RefundProcessorFactory $processorFactory,
        private readonly UpdateOrderRefundedAmountAction $updateOrderRefundedAmount,
        private readonly MixedOrderRefundCalculator $calculator,
        private readonly EnrollmentRevocationService $revocations,
        private readonly RefundPaymentGuard $paymentGuard,
        private readonly BundlePurchaseRefundGuard $bundleGuard,
    ) {}

    /**
     * @return Collection<int, Refund>
     */
    public function handle(Order $order, RefundOrderData $data): Collection
    {
        $state = DB::transaction(function () use ($order, $data) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->loadMissing([
                'items.enrollment', 'items.refunds',
                'bundlePurchases.components.enrollment', 'bundlePurchases.components.refunds',
                'payments',
            ]);

            $units = $this->discoverUnits($order);

            $payment         = $this->paymentGuard->resolveCompletedPayment($order);
            $paymentMethod   = $payment?->method->value ?? PaymentMethodEnum::BANK_TRANSFER->value;
            $processor       = $this->processorFactory->make($paymentMethod);
            $requiresGateway = $paymentMethod === PaymentMethodEnum::DIGIPAY->value && ! $data->skip_gateway;

            $this->assertGatewayCanRefundEveryUnit($order, $units, $requiresGateway);

            $calculation = $this->calculator->calculate($units, $data->deduction_amount, $data->deduction_percent);

            if ($requiresGateway && $payment instanceof Payment) {
                $this->paymentGuard->assertRefundWithinPaymentLimit($payment, $calculation['refund_amount']);
            }

            /** @var Collection<int, Refund> $allRefunds */
            $allRefunds = new Collection();
            /** @var list<array{is_bundle: bool, bundle_purchase: ?BundlePurchase, refunds: Collection<int, Refund>}> $stateUnits */
            $stateUnits = [];
            foreach ($units as $index => $unit) {
                $unitCalculation = $calculation['units'][$index];

                /** @var Collection<int, Refund> $unitRefunds */
                $unitRefunds = new Collection();
                foreach ($unitCalculation['components'] as $line) {
                    $refund = Refund::create([
                        'order_id'            => $order->id,
                        'order_item_id'       => $line['order_item_id'],
                        'payment_id'          => $payment?->id,
                        'customer_id'         => $order->customer_id,
                        'amount'              => $line['refund_amount'],
                        'deduction_amount'    => $line['effective_deduction_amount'],
                        'status'              => RefundStatusEnum::PROCESSING,
                        'transaction_details' => $this->transactionDetails($order, $unit, $unitCalculation, $line, $data),
                        'refunded_at'         => null,
                        'admin_notes'         => $data->admin_notes,
                    ]);

                    $unitRefunds->push($refund);
                    $allRefunds->push($refund);
                }

                $stateUnits[] = [
                    'is_bundle'       => $unit['is_bundle'],
                    'bundle_purchase' => $unit['bundle_purchase'],
                    'refunds'         => $unitRefunds,
                ];
            }

            // Return everything we need for the next phases
            return (object) [
                'order'             => $order,
                'payment'           => $payment,
                'paymentMethod'     => $paymentMethod,
                'processor'         => $processor,
                'requiresGateway'   => $requiresGateway,
                'units'             => $stateUnits,
                'refunds'           => $allRefunds,
                'totalRefundAmount' => $calculation['refund_amount'],
            ];
        });

        $gatewayTrackingCode = null;
        if ($state->requiresGateway) {
            try {
                // Digipay single bulk processing via HTTP API
                $gatewayTrackingCode = $state->processor->process(
                    new Refund(['payment_id' => $state->payment->id]),
                    $state->order,
                    $state->totalRefundAmount,
                );
            } catch (Exception $e) {
                // HTTP API Failed. Revert 'PROCESSING' refunds to 'FAILED'
                $errorMessage = $e->getMessage();
                Refund::whereIn('id', $state->refunds->pluck('id'))->update([
                    'status'      => RefundStatusEnum::FAILED,
                    'admin_notes' => DB::raw("CONCAT(COALESCE(admin_notes, ''), '\n', ".DB::connection()->getPdo()->quote($errorMessage).')'),
                ]);

                if ($e instanceof RefundGatewayException) {
                    throw new RefundValidationException($errorMessage);
                }
                throw $e;
            }
        }

        $attemptIds = [];
        try {
            $attemptIds = DB::transaction(function () use ($state, $data, $gatewayTrackingCode): array {
                // Re-lock the order to safely apply status updates
                $order = Order::query()->whereKey($state->order->id)->lockForUpdate()->firstOrFail();

                $attemptIds = [];
                foreach ($state->units as $unit) {
                    foreach ($unit['refunds'] as $processingRefund) {
                        $refund = Refund::query()->whereKey($processingRefund->id)->lockForUpdate()->firstOrFail();
                        if ($refund->status === RefundStatusEnum::COMPLETED) {
                            continue;
                        }

                        if ($state->paymentMethod === PaymentMethodEnum::WALLET->value && ! $data->skip_gateway) {
                            $state->processor->process($refund, $order, $refund->amount);
                        }

                        $adminNotes = $refund->admin_notes;
                        if ($data->skip_gateway) {
                            $adminNotes = mb_trim(($adminNotes ?? '')."\n".__('messages.admin.gateway_skipped_note', ['datetime' => now()->toDateTimeString()]));
                        }

                        $transactionDetails = $refund->transaction_details ?? [];
                        if ($gatewayTrackingCode) {
                            $transactionDetails = array_merge($transactionDetails, ['gateway_tracking_code' => $gatewayTrackingCode]);
                        }

                        $refund->update([
                            'status'              => RefundStatusEnum::COMPLETED,
                            'transaction_details' => $transactionDetails,
                            'refunded_at'         => now(),
                            'admin_notes'         => $adminNotes,
                        ]);

                        $item = OrderItem::query()->whereKey($refund->order_item_id)->lockForUpdate()->firstOrFail();
                        $item->update([
                            'status'         => OrderItemStatusEnum::REFUNDED,
                            'total_refunded' => $refund->amount,
                            'qty_refunded'   => $item->qty_ordered,
                        ]);

                        if ($unit['is_bundle']) {
                            // The component keeps its seat (SUSPENDED still
                            // occupies) and locally loses access immediately;
                            // the Enrollment only becomes CANCELLED once every
                            // required provider revocation has succeeded.
                            $enrollment = $item->enrollment;
                            if ($enrollment && ! $enrollment->isRevocationComplete()) {
                                $enrollment->enrollment_status = EnrollmentStatusEnum::SUSPENDED;
                                $enrollment->save();
                            }
                        } else {
                            $this->orderStatusService->updateEnrollmentStatus($item);
                        }

                        RefundCompletedEvent::dispatch($refund);
                    }

                    $purchase = $unit['bundle_purchase'];
                    if ($purchase instanceof BundlePurchase) {
                        foreach ($purchase->components as $component) {
                            if ($component->enrollment) {
                                $attemptIds = array_merge($attemptIds, $this->revocations->begin($component->enrollment));
                            }
                        }
                    }
                }

                $this->orderStatusService->updateParentOrderStatus($order->fresh());
                $this->updateOrderRefundedAmount->handle($order->fresh());

                return $attemptIds;
            });
        } catch (Exception $e) {
            if ($state->requiresGateway) {
                // TODO: need to notify Staff
                // CRITICAL ERROR: We gave the user money via Digipay, but our DB failed to save the changes!
                Log::emergency('CRITICAL ERROR: Gateway API succeeded but Database update failed.', [
                    'order_id'      => $state->order->id,
                    'refund_ids'    => $state->refunds->pluck('id')->toArray(),
                    'tracking_code' => $gatewayTrackingCode,
                    'error'         => $e->getMessage(),
                ]);
            } else {
                // LOCAL FAILURE: It was just a DB error (like Wallet logic failing).
                // Because the transaction rolled back cleanly, no money was moved.
                // We just need to mark Phase 1 refunds as FAILED.
                Refund::whereIn('id', $state->refunds->pluck('id'))
                    ->update(['status' => RefundStatusEnum::FAILED]);
            }
            throw $e;
        }

        $this->revocations->dispatchAttempts($attemptIds);

        return Refund::query()
            ->whereIn('id', $state->refunds->pluck('id'))
            ->with('order')
            ->get();
    }

    /**
     * Discover every commercial unit exactly once: standalone Order Items
     * individually and each Bundle Purchase as one indivisible unit. Internal
     * Bundle Component Order Items are never discovered on their own.
     *
     * @return list<array{
     *     is_bundle: bool,
     *     bundle_purchase: ?BundlePurchase,
     *     base_value: int,
     *     paid_value: int,
     *     components: list<array{order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int}>
     * }>
     */
    private function discoverUnits(Order $order): array
    {
        $units = [];

        foreach ($order->standaloneItems()->with('refunds')->get() as $item) {
            if ($this->isItemClosed($item)) {
                continue;
            }
            // A unit with an in-flight refund would make this a partial
            // operation, so it aborts the whole refund like a Bundle unit.
            if ($this->hasActiveRefund($item)) {
                throw new RefundValidationException(__('messages.order.refund.refund_request_exists'));
            }

            $baseValue = $item->base_price_amount > 0
                ? $item->base_price_amount
                : $item->price * $item->qty_ordered;
            $paidValue = $this->calculateAmountPaidForItem($order, $item);

            $units[] = $this->unit(
                isBundle: false,
                bundlePurchase: null,
                baseValue: max(0, (int) $baseValue),
                paidValue: max(0, $paidValue),
                components: [[
                    'order_item_id'              => $item->id,
                    'product_delivery_option_id' => $item->product_delivery_option_id,
                    'base_price'                 => max(0, (int) $baseValue),
                    'paid_amount'                => max(0, $paidValue),
                ]],
            );
        }

        foreach ($order->bundlePurchases as $purchase) {
            if ($this->bundleGuard->isFullyHandled($purchase)) {
                continue;
            }

            $this->bundleGuard->assertRefundable($purchase);

            $components = $purchase->components
                ->map(fn (OrderItem $component): array => [
                    'order_item_id'              => $component->id,
                    'product_delivery_option_id' => $component->product_delivery_option_id,
                    'base_price'                 => max(0, (int) $component->price),
                    'paid_amount'                => max(0, $component->paid_amount),
                ])
                ->values()
                ->all();

            // Derive the unit totals from the same snapshots the lines use, so
            // the single gateway amount always equals the sum of the refunds.
            $units[] = $this->unit(
                isBundle: true,
                bundlePurchase: $purchase,
                baseValue: (int) array_sum(array_column($components, 'base_price')),
                paidValue: (int) array_sum(array_column($components, 'paid_amount')),
                components: $components,
            );
        }

        if ($units === []) {
            throw new RefundValidationException(__('messages.order.refund.no_refundable_items'));
        }

        return $units;
    }

    /**
     * @param  list<array{order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int}>  $components
     * @return array{
     *     is_bundle: bool,
     *     bundle_purchase: ?BundlePurchase,
     *     base_value: int,
     *     paid_value: int,
     *     components: list<array{order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int}>
     * }
     */
    private function unit(
        bool $isBundle,
        ?BundlePurchase $bundlePurchase,
        int $baseValue,
        int $paidValue,
        array $components,
    ): array {
        return [
            'is_bundle'       => $isBundle,
            'bundle_purchase' => $bundlePurchase,
            'base_value'      => $baseValue,
            'paid_value'      => $paidValue,
            'components'      => $components,
        ];
    }

    /**
     * Digipay cannot issue a partial refund unless explicitly enabled, and
     * skipping any commercial unit makes this a partial refund.
     *
     * @param  list<array{is_bundle: bool, bundle_purchase: ?BundlePurchase, base_value: int, paid_value: int, components: list<array<string, int>>}>  $units
     */
    private function assertGatewayCanRefundEveryUnit(Order $order, array $units, bool $requiresGateway): void
    {
        if (! $requiresGateway || config('payments.digipay.allow_partial_refund')) {
            return;
        }

        $commercialUnitCount = $order->items
            ->filter(fn (OrderItem $item): bool => $item->bundle_purchase_id === null)
            ->count()
            + $order->bundlePurchases->count();

        if (count($units) < $commercialUnitCount) {
            throw new RefundValidationException(__('messages.order.refund.digipay_partial_refund_not_supported'));
        }
    }

    /**
     * @param  array{is_bundle: bool, bundle_purchase: ?BundlePurchase, base_value: int, paid_value: int, components: list<array<string, int>>}  $unit
     * @param  array{policy_deduction_amount: int, effective_deduction_amount: int, refund_amount: int, components: list<array<string, int>>}  $unitCalculation
     * @param  array<string, int>  $line
     * @return array<string, mixed>
     */
    private function transactionDetails(
        Order $order,
        array $unit,
        array $unitCalculation,
        array $line,
        RefundOrderData $data,
    ): array {
        $details = [
            'receiver_name'     => $data->receiver_name,
            'card_number'       => $data->card_number,
            'iban'              => $data->iban,
            'full_order_refund' => [
                'order_id'                   => $order->id,
                'commercial_unit'            => $unit['is_bundle'] ? 'bundle' : 'standalone',
                'base_value'                 => $unit['base_value'],
                'paid_value'                 => $unit['paid_value'],
                'policy_deduction_amount'    => $unitCalculation['policy_deduction_amount'],
                'effective_deduction_amount' => $unitCalculation['effective_deduction_amount'],
                'refund_amount'              => $unitCalculation['refund_amount'],
            ],
        ];

        $purchase = $unit['bundle_purchase'];
        if ($purchase instanceof BundlePurchase) {
            $details['bundle_refund'] = [
                'bundle_purchase_id'            => $purchase->id,
                'bundle_sku'                    => $purchase->sku,
                'base_value'                    => $purchase->base_value,
                'paid_amount'                   => $purchase->selling_price,
                'policy_deduction_amount'       => $unitCalculation['policy_deduction_amount'],
                'effective_deduction_amount'    => $unitCalculation['effective_deduction_amount'],
                'policy_component_deduction'    => $line['policy_deduction_amount'],
                'effective_component_deduction' => $line['effective_deduction_amount'],
                'redistributed_amount'          => $line['redistributed_amount'],
            ];
        }

        return $details;
    }

    private function isItemClosed(OrderItem $item): bool
    {
        return in_array($item->status, [OrderItemStatusEnum::REFUNDED, OrderItemStatusEnum::CANCELLED], true);
    }

    private function hasActiveRefund(OrderItem $item): bool
    {
        return $item->refunds->contains(
            fn (Refund $refund): bool => $refund->status !== RefundStatusEnum::FAILED
        );
    }

    private function calculateAmountPaidForItem(Order $order, OrderItem $item): int
    {
        if ($order->balance_due <= 0) {
            return (int) (($item->price - $item->discount_amount + $item->tax_amount) * $item->qty_ordered);
        }

        return (int) $item->total;
    }
}
