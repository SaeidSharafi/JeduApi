<?php

declare(strict_types=1);

namespace App\Actions\Admin\Refund;

use App\Data\Admin\Refund\BundleRefundComponentData;
use App\Data\Admin\Refund\BundleRefundData;
use App\Data\Admin\Refund\RefundBundlePurchaseData;
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
use App\Services\BundleRefundDeductionCalculator;
use App\Services\OrderStatusService;
use App\Services\Payment\Refund\RefundProcessorFactory;
use App\Services\Provisioning\EnrollmentRevocationService;
use App\Services\RefundPaymentGuard;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Refunds one whole Bundle Purchase as a single indivisible commercial
 * operation.
 *
 * Every component line is refunded together; partial commercial refunds of
 * selected components are impossible. The deduction policy is distributed
 * deterministically across the immutable component snapshots, the financial
 * refund completes first, and only then do the affected Enrollments become
 * revocation-pending and asynchronous provider revocation begin.
 */
final class RefundBundlePurchaseAction
{
    public function __construct(
        private readonly RefundProcessorFactory $processorFactory,
        private readonly UpdateOrderRefundedAmountAction $updateOrderRefundedAmount,
        private readonly OrderStatusService $orderStatusService,
        private readonly BundleRefundDeductionCalculator $calculator,
        private readonly EnrollmentRevocationService $revocations,
        private readonly RefundPaymentGuard $paymentGuard,
        private readonly BundlePurchaseRefundGuard $bundleGuard,
    ) {}

    public function handle(BundlePurchase $bundlePurchase, RefundBundlePurchaseData $data): BundleRefundData
    {
        $state = DB::transaction(function () use ($bundlePurchase, $data) {
            $order    = Order::query()->whereKey($bundlePurchase->order_id)->lockForUpdate()->firstOrFail();
            $purchase = BundlePurchase::query()->whereKey($bundlePurchase->id)->lockForUpdate()->firstOrFail();
            $purchase->loadMissing('components.enrollment', 'components.productDeliveryOption');

            $this->assertRefundable($order, $purchase);

            $payment         = $this->paymentGuard->resolveCompletedPayment($order);
            $paymentMethod   = $payment?->method->value ?? PaymentMethodEnum::BANK_TRANSFER->value;
            $processor       = $this->processorFactory->make($paymentMethod);
            $requiresGateway = $paymentMethod === PaymentMethodEnum::DIGIPAY->value && ! $data->skip_gateway;

            if ($requiresGateway && ! config('payments.digipay.allow_partial_refund')) {
                $this->assertPaymentCanCoverPurchaseAlone($order, $purchase);
            }

            $components = $purchase->components
                ->map(fn (OrderItem $item): array => [
                    'order_item_id'              => $item->id,
                    'product_delivery_option_id' => $item->product_delivery_option_id,
                    'base_price'                 => (int) $item->price,
                    'paid_amount'                => $item->paid_amount,
                ])
                ->values()
                ->all();

            $calculation = $this->calculator->calculate(
                $purchase->selling_price,
                $components,
                $this->resolvePolicyTarget($purchase->base_value, $data),
            );

            if ($requiresGateway && $payment instanceof Payment) {
                $this->paymentGuard->assertRefundWithinPaymentLimit($payment, $calculation['refund_amount']);
            }

            /** @var Collection<int, Refund> $refunds */
            $refunds = new Collection();
            foreach ($calculation['components'] as $component) {
                $refunds->push(Refund::create([
                    'order_id'            => $order->id,
                    'order_item_id'       => $component['order_item_id'],
                    'payment_id'          => $payment?->id,
                    'customer_id'         => $order->customer_id,
                    'amount'              => $component['refund_amount'],
                    'deduction_amount'    => $component['effective_deduction_amount'],
                    'status'              => RefundStatusEnum::PROCESSING,
                    'transaction_details' => [
                        'receiver_name' => $data->receiver_name,
                        'card_number'   => $data->card_number,
                        'iban'          => $data->iban,
                        'bundle_refund' => [
                            'bundle_purchase_id'            => $purchase->id,
                            'bundle_sku'                    => $purchase->sku,
                            'base_value'                    => $purchase->base_value,
                            'paid_amount'                   => $purchase->selling_price,
                            'policy_deduction_amount'       => $calculation['policy_deduction_amount'],
                            'effective_deduction_amount'    => $calculation['effective_deduction_amount'],
                            'policy_component_deduction'    => $component['policy_deduction_amount'],
                            'effective_component_deduction' => $component['effective_deduction_amount'],
                            'redistributed_amount'          => $component['redistributed_amount'],
                        ],
                    ],
                    'refunded_at' => null,
                    'admin_notes' => $data->admin_notes,
                ]));
            }

            return (object) [
                'order'             => $order,
                'purchase'          => $purchase,
                'payment'           => $payment,
                'paymentMethod'     => $paymentMethod,
                'processor'         => $processor,
                'requiresGateway'   => $requiresGateway,
                'calculation'       => $calculation,
                'refunds'           => $refunds,
                'totalRefundAmount' => $calculation['refund_amount'],
            ];
        });

        $gatewayTrackingCode = null;
        if ($state->requiresGateway) {
            try {
                $gatewayTrackingCode = $state->processor->process(
                    new Refund(['payment_id' => $state->payment->id]),
                    $state->order,
                    $state->totalRefundAmount,
                );
            } catch (Exception $e) {
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
                $order      = Order::query()->whereKey($state->order->id)->lockForUpdate()->firstOrFail();
                $attemptIds = [];

                foreach ($state->refunds as $processingRefund) {
                    $refund = Refund::query()->whereKey($processingRefund->id)->lockForUpdate()->firstOrFail();
                    if ($refund->status === RefundStatusEnum::COMPLETED) {
                        continue;
                    }

                    $transactionDetails = $refund->transaction_details ?? [];
                    if ($gatewayTrackingCode) {
                        $transactionDetails['gateway_tracking_code'] = $gatewayTrackingCode;
                    }

                    $adminNotes = $refund->admin_notes;
                    if ($data->skip_gateway) {
                        $adminNotes = mb_trim(($adminNotes ?? '')."\n".__('messages.admin.gateway_skipped_note', ['datetime' => now()->toDateTimeString()]));
                    }

                    if ($state->paymentMethod === PaymentMethodEnum::WALLET->value && ! $data->skip_gateway) {
                        $state->processor->process($refund, $order, $refund->amount);
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

                    // The component keeps its seat (SUSPENDED still occupies)
                    // and locally loses access immediately; the Enrollment only
                    // becomes CANCELLED once every required provider revocation
                    // has succeeded.
                    $enrollment = $item->enrollment;
                    if ($enrollment && ! $enrollment->isRevocationComplete()) {
                        $enrollment->enrollment_status = EnrollmentStatusEnum::SUSPENDED;
                        $enrollment->save();
                    }

                    RefundCompletedEvent::dispatch($refund);
                }

                $this->orderStatusService->updateParentOrderStatus($order->fresh());
                $this->updateOrderRefundedAmount->handle($order->fresh());

                foreach ($state->purchase->components as $component) {
                    if ($component->enrollment) {
                        $attemptIds = array_merge($attemptIds, $this->revocations->begin($component->enrollment));
                    }
                }

                return $attemptIds;
            });
        } catch (Exception $e) {
            if ($state->requiresGateway) {
                Log::emergency('CRITICAL ERROR: Bundle refund gateway succeeded but Database update failed.', [
                    'order_id'      => $state->order->id,
                    'refund_ids'    => $state->refunds->pluck('id')->toArray(),
                    'tracking_code' => $gatewayTrackingCode,
                    'error'         => $e->getMessage(),
                ]);
            } else {
                Refund::whereIn('id', $state->refunds->pluck('id'))
                    ->update(['status' => RefundStatusEnum::FAILED]);
            }
            throw $e;
        }

        $this->revocations->dispatchAttempts($attemptIds);

        return $this->buildResponse($state->purchase, $state->calculation, $state->refunds);
    }

    private function assertRefundable(Order $order, BundlePurchase $purchase): void
    {
        if ($order->total_paid <= 0) {
            throw new RefundValidationException(__('messages.order.refund.no_completed_payments'));
        }

        if ($purchase->components->isEmpty()) {
            throw new RefundValidationException(__('messages.order.refund.no_refundable_items'));
        }

        $this->bundleGuard->assertRefundable($purchase);
    }

    private function assertPaymentCanCoverPurchaseAlone(Order $order, BundlePurchase $purchase): void
    {
        $hasOtherUnits = $order->standaloneItems()->exists()
            || $order->bundlePurchases()->whereKeyNot($purchase->id)->exists();

        if ($hasOtherUnits) {
            throw new RefundValidationException(__('messages.order.refund.digipay_partial_refund_not_supported'));
        }
    }

    private function resolvePolicyTarget(int $baseValue, RefundBundlePurchaseData $data): int
    {
        $percentTarget = $data->deduction_percent !== null
            ? (int) floor(($baseValue * $data->deduction_percent) / 100)
            : null;

        if ($data->deduction_amount !== null && $percentTarget !== null) {
            if ($percentTarget !== $data->deduction_amount) {
                throw new RefundValidationException(__('messages.order.refund.deduction_conflict'));
            }

            return $data->deduction_amount;
        }

        return $data->deduction_amount ?? $percentTarget ?? 0;
    }

    /**
     * @param  array{
     *     policy_deduction_amount: int,
     *     effective_deduction_amount: int,
     *     refund_amount: int,
     *     components: list<array{
     *         order_item_id: int, product_delivery_option_id: int, base_price: int, paid_amount: int,
     *         policy_deduction_amount: int, effective_deduction_amount: int,
     *         redistributed_amount: int, refund_amount: int
     *     }>
     * }  $calculation
     * @param  Collection<int, Refund>  $refunds
     */
    private function buildResponse(BundlePurchase $purchase, array $calculation, Collection $refunds): BundleRefundData
    {
        $purchase = $purchase->fresh(['components.enrollment', 'order']);
        $items    = $purchase->components->keyBy('id');
        $refunds  = $refunds->keyBy('order_item_id');

        $components = [];
        foreach ($calculation['components'] as $component) {
            $item   = $items->get($component['order_item_id']);
            $refund = $refunds->get($component['order_item_id']);

            if (! $item instanceof OrderItem) {
                throw new LogicException("Bundle component Order Item {$component['order_item_id']} is missing.");
            }

            $components[] = new BundleRefundComponentData(
                order_item_id: $component['order_item_id'],
                product_delivery_option_id: $component['product_delivery_option_id'],
                name: $item->name,
                sku: $item->sku,
                base_price: $component['base_price'],
                paid_amount: $component['paid_amount'],
                policy_deduction_amount: $component['policy_deduction_amount'],
                effective_deduction_amount: $component['effective_deduction_amount'],
                redistributed_amount: $component['redistributed_amount'],
                refund_amount: $component['refund_amount'],
                refund_id: $refund instanceof Refund ? $refund->id : null,
                revocation_status: $item->enrollment?->revocation_status,
            );
        }

        return new BundleRefundData(
            bundle_purchase_id: $purchase->id,
            bundle_name: $purchase->bundle_name,
            sku: $purchase->sku,
            base_value: $purchase->base_value,
            paid_amount: $purchase->selling_price,
            policy_deduction_amount: $calculation['policy_deduction_amount'],
            effective_deduction_amount: $calculation['effective_deduction_amount'],
            refund_amount: $calculation['refund_amount'],
            status: $purchase->status,
            components: new Collection($components),
        );
    }
}
