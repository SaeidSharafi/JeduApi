<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay;

use App\Exceptions\Gateway\DigipayException;
use App\Models\Payment;
use App\Services\Payment\Digipay\Data\DeliverResponse;
use App\Services\Payment\Digipay\Data\RefundInquiryResponse;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\Data\ReverseResponse;

final class DigipayAdminService
{
    // Payment types requiring delivery confirmation before funds are released
    public const DELIVERY_REQUIRED_TYPES = [5, 13]; // CREDIT, BNPL

    public function __construct(
        private DigipayClient $client,
    ) {}

    public function refund(Payment $payment, ?int $amount = null): RefundResponse
    {
        $gatewayResponse = $this->getGatewayResponse($payment);
        $refundAmount    = $amount ?? $payment->amount;

        return $this->client->refund(
            providerId: 'REFUND-'.$payment->id.'-'.time(),
            amount: $refundAmount,
            saleTrackingCode: $gatewayResponse['tracking_code'],
            type: $gatewayResponse['payment_gateway'] ?? 0,
        );
    }

    public function deliver(Payment $payment): DeliverResponse
    {
        $gatewayResponse = $this->getGatewayResponse($payment);
        $order           = $payment->order;

        $order->load('items.productDeliveryOption');

        $products = $order->items
            ->map(fn ($item): string => 'product-'.$item->productDeliveryOption->product_id)
            ->values()
            ->all();

        return $this->client->deliver(
            trackingCode: $gatewayResponse['tracking_code'],
            invoiceNumber: (string) $order->id,
            products: $products,
            type: $gatewayResponse['payment_gateway'] ?? 0,
        );
    }

    public function inquireRefund(string $refundProviderId, int $type): RefundInquiryResponse
    {
        return $this->client->inquireRefund($refundProviderId, $type);
    }

    public function reverse(Payment $payment): ReverseResponse
    {
        $gatewayResponse = $this->getGatewayResponse($payment);

        return $this->client->reverse(
            purchaseTrackingCode: $gatewayResponse['tracking_code'],
            providerId: $gatewayResponse['provider_id'],
        );
    }

    public function requiresDeliveryConfirmation(Payment $payment): bool
    {
        $gatewayResponse = $this->getGatewayResponse($payment);

        return in_array(
            (int) ($gatewayResponse['payment_gateway'] ?? -1),
            self::DELIVERY_REQUIRED_TYPES,
            true
        );
    }

    private function getGatewayResponse(Payment $payment): array
    {
        $response = $payment->transactions()->latest()->first()->gateway_response;

        if (empty($response['tracking_code'])) {
            throw new DigipayException(
                __('payment_gateways.digipay.errors.no_tracking_code', ['id' => $payment->id])
            );
        }

        return $response;
    }
}
