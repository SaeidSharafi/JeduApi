<?php

declare(strict_types=1);

namespace App\Services\Payment\Refund;

use App\Contracts\Payment\RefundProcessorInterface;
use App\Exceptions\Gateway\DigipayException;
use App\Exceptions\RefundGatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payment\Digipay\DigipayAdminService;
use Illuminate\Support\Facades\Log;
use SmartCache\Facades\SmartCache;

final readonly class DigipayRefundProcessor implements RefundProcessorInterface
{
    public function __construct(
        private DigipayAdminService $digipayService,
    ) {}

    public function process(Refund $refund, Order $order, int $amount): ?string
    {
        $payment = $this->resolvePayment($refund, $order);

        if (! $payment) {
            Log::channel('digipay')->warning('[Refund] No Digipay payment found for order', [
                'order_id' => $order->id,
            ]);

            return null;
        }

        $lockKey = "digipay_refund_payment_{$payment->id}";

        return SmartCache::lock($lockKey, 15)->block(5, function () use ($payment, $order, $amount): ?string {
            // Cumulative cap check (serialized per payment to avoid concurrent over-refund race)
            $alreadyRefunded = Refund::query()
                ->where('payment_id', $payment->id)
                ->where('status', \App\Enums\Order\RefundStatusEnum::COMPLETED)
                ->sum('amount');

            if (($alreadyRefunded + $amount) > $payment->amount) {
                throw new RefundGatewayException(__('messages.exceptions.refund_exceeds_payment', [
                    'amount'           => $amount,
                    'payment_id'       => $payment->id,
                    'payment_amount'   => $payment->amount,
                    'already_refunded' => $alreadyRefunded,
                ]));
            }

            // BNPL/CREDIT delivery guard
            $this->guardDeliveryConfirmation($payment, $order);

            try {
                $response = $this->digipayService->refund($payment, $amount);

                Log::channel('digipay')->info('[Digipay] Refund successful', [
                    'order_id'      => $order->id,
                    'amount'        => $amount,
                    'tracking_code' => $response->trackingCode,
                ]);

                return $response->trackingCode;
            } catch (DigipayException $e) {
                Log::channel('digipay')->error('[Digipay] Refund failed', [
                    'order_id' => $order->id,
                    'amount'   => $amount,
                    'error'    => $e->getMessage(),
                ]);

                throw new RefundGatewayException(
                    __('payment_gateways.digipay.errors.refund_failed', ['details' => $e->getMessage()]),
                    previous: $e,
                );
            }
        });
    }

    private function resolvePayment(Refund $refund, Order $order): ?Payment
    {
        // Use refund.payment_id if populated, otherwise fall back to oldest completed Digipay payment
        if ($refund->payment_id) {
            $payment = $refund->payment;

            return $payment instanceof Payment ? $payment : null;
        }

        return $order->payments()
            ->where('method', \App\Enums\Payment\PaymentMethodEnum::DIGIPAY)
            ->where('status', \App\Enums\Payment\PaymentStatusEnum::COMPLETED)
            ->oldest()
            ->first();
    }

    private function guardDeliveryConfirmation(Payment $payment, Order $order): void
    {
        $gatewayType = $payment->latestTransaction?->gateway_response['payment_gateway'] ?? null;

        if (in_array((int) $gatewayType, DigipayAdminService::DELIVERY_REQUIRED_TYPES, true)) {
            $deliveryConfirmed = $payment->latestTransaction?->gateway_response['delivery_confirmed'] ?? false;

            if (! $deliveryConfirmed) {
                Log::channel('digipay')->warning('[Digipay] Refund attempted on BNPL/CREDIT payment before delivery confirmation', [
                    'order_id'     => $order->id,
                    'payment_id'   => $payment->id,
                    'gateway_type' => $gatewayType,
                ]);
            }
        }
    }
}
