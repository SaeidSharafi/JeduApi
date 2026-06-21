<?php

declare(strict_types=1);

namespace App\Services\Payment\Digipay;

use App\Services\Payment\Digipay\Data\DeliverResponse;
use App\Services\Payment\Digipay\Data\RefundInquiryResponse;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\Data\ReverseResponse;
use App\Services\Payment\Digipay\Data\TicketResponse;
use App\Services\Payment\Digipay\Data\VerifyResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class DigipayClient
{
    public function __construct(
        private DigipayAuthenticator $authenticator,
        private DigipayConfigRepository $config,
    ) {}

    public function createTicket(
        int $amount,
        string $cellNumber,
        string $providerId,
        string $callbackUrl,
        ?string $description = null,
    ): TicketResponse {
        $body = [
            'amount'      => $amount,
            'cellNumber'  => $cellNumber,
            'providerId'  => $providerId,
            'callbackUrl' => $callbackUrl,
        ];

        if ($description !== null) {
            $body['additionalInfo'] = ['description' => $description];
        }

        $ticketType = config('digipay.ticket_type', 11);
        $response   = TicketResponse::fromResponse(
            $this->post(config('digipay.paths.ticket').'?type='.$ticketType, $body)
        );

        if (! $response->isSuccessful()) {
            throw new DigipayException(
                "Digipay ticket creation failed: {$response->message}",
                $response->statusCode,
            );
        }

        return $response;
    }

    public function verify(string $trackingCode, string $providerId, int $type): VerifyResponse
    {
        $response = VerifyResponse::fromResponse(
            $this->post(config('digipay.paths.verify').'?type='.$type, [
                'trackingCode' => $trackingCode,
                'providerId'   => $providerId,
            ])
        );

        if (! $response->isSuccessful()) {
            throw new DigipayException(
                "Digipay verification failed: {$response->message}",
                $response->statusCode,
                ['tracking_code' => $trackingCode, 'provider_id' => $providerId],
            );
        }

        return $response;
    }

    public function refund(
        string $providerId,
        int $amount,
        string $saleTrackingCode,
        int $type,
    ): RefundResponse {
        $response = RefundResponse::fromResponse(
            $this->post(config('digipay.paths.refund').'?type='.$type, [
                'providerId'       => $providerId,
                'amount'           => $amount,
                'saleTrackingCode' => $saleTrackingCode,
            ])
        );

        if (! $response->isSuccessful()) {
            throw new DigipayException(
                "Digipay refund failed: {$response->message}",
                $response->statusCode,
            );
        }

        return $response;
    }

    public function deliver(
        string $trackingCode,
        string $invoiceNumber,
        array $products,
        int $type,
    ): DeliverResponse {
        $response = DeliverResponse::fromResponse(
            $this->post(config('digipay.paths.deliver').'?type='.$type, [
                'deliveryDate'  => (int) (now()->timestamp * 1000),
                'invoiceNumber' => $invoiceNumber,
                'trackingCode'  => $trackingCode,
                'products'      => $products,
            ])
        );

        if (! $response->isSuccessful()) {
            throw new DigipayException(
                "Digipay delivery confirmation failed: {$response->message}",
                $response->statusCode,
            );
        }

        return $response;
    }

    public function inquireRefund(string $refundProviderId, int $type): RefundInquiryResponse
    {
        $response = RefundInquiryResponse::fromResponse(
            $this->post(
                config('digipay.paths.refund').'/'.$refundProviderId.'?type='.$type,
                []
            )
        );

        if (! $response->isSuccessful()) {
            throw new DigipayException(
                "Digipay refund inquiry failed: {$response->message}",
                $response->statusCode,
            );
        }

        return $response;
    }

    public function reverse(string $purchaseTrackingCode, string $providerId): ReverseResponse
    {
        $response = ReverseResponse::fromResponse(
            $this->post(config('digipay.paths.reverse'), [
                'purchaseTrackingCode' => $purchaseTrackingCode,
                'providerId'           => $providerId,
            ])
        );

        if (! $response->isSuccessful()) {
            throw new DigipayException(
                "Digipay reverse failed: {$response->message}",
                $response->statusCode,
            );
        }

        return $response;
    }

    private function post(string $path, array $data): array
    {
        $token = $this->authenticator->getAccessToken();
        $url   = $this->config->getBaseUrl().$path;

        Log::channel(config('digipay.logging.channel', 'stack'))->info('[Digipay] Request', [
            'url'  => $url,
            'body' => $this->maskSensitive($data),
        ]);

        $response = Http::timeout($this->config->getTimeout())
            ->withToken($token)
            ->withHeaders([
                'Agent'           => 'WEB',
                'Digipay-Version' => config('digipay.default_api_version', '2022-02-02'),
            ])
            ->post($url, $data);

        Log::channel(config('digipay.logging.channel', 'stack'))->info('[Digipay] Response', [
            'url'    => $url,
            'status' => $response->status(),
            'body'   => $this->maskSensitive($response->json() ?? []),
        ]);

        if ($response->failed()) {
            throw new DigipayException(
                "Digipay HTTP error: {$response->status()}",
                $response->status(),
            );
        }

        return $response->json() ?? [];
    }

    private function maskSensitive(array $data): array
    {
        $sensitiveFields = config('digipay.logging.sensitive_fields', []);

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***';
            }
        }

        return $data;
    }
}
