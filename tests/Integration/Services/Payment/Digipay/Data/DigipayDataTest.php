<?php

declare(strict_types=1);

use App\Services\Payment\Digipay\Data\CallbackPayload;
use App\Services\Payment\Digipay\Data\DeliverResponse;
use App\Services\Payment\Digipay\Data\RefundInquiryResponse;
use App\Services\Payment\Digipay\Data\RefundResponse;
use App\Services\Payment\Digipay\Data\ReverseResponse;
use App\Services\Payment\Digipay\Data\TicketResponse;
use App\Services\Payment\Digipay\Data\VerifyResponse;

describe('TicketResponse', function (): void {
    it('parses successful response', function (): void {
        $response = TicketResponse::fromResponse([
            'redirectUrl' => 'https://pay.test/abc',
            'ticket'      => 'ticket-123',
            'result'      => ['status' => 0, 'message' => 'OK'],
        ]);

        expect($response->redirectUrl)->toBe('https://pay.test/abc')
            ->and($response->ticket)->toBe('ticket-123')
            ->and($response->statusCode)->toBe(0)
            ->and($response->message)->toBe('OK')
            ->and($response->isSuccessful())->toBeTrue();
    });

    it('parses failed response', function (): void {
        $response = TicketResponse::fromResponse([
            'result' => ['status' => 100, 'message' => 'Invalid amount'],
        ]);

        expect($response->statusCode)->toBe(100)
            ->and($response->isSuccessful())->toBeFalse();
    });

    it('handles missing keys gracefully', function (): void {
        $response = TicketResponse::fromResponse([]);

        expect($response->redirectUrl)->toBe('')
            ->and($response->ticket)->toBe('')
            ->and($response->statusCode)->toBe(-1)
            ->and($response->message)->toBe('');
    });
});

describe('VerifyResponse', function (): void {
    it('parses successful verification', function (): void {
        $response = VerifyResponse::fromResponse([
            'trackingCode'   => 'TCK-001',
            'providerId'     => 'PRV-001',
            'amount'         => 150000,
            'rrn'            => '123456789',
            'maskedPan'      => '6037***1234',
            'pspName'        => 'Mellat',
            'terminalId'     => 'T123',
            'paymentGateway' => 1,
            'result'         => ['status' => 0, 'message' => 'Verified'],
        ]);

        expect($response->trackingCode)->toBe('TCK-001')
            ->and($response->isSuccessful())->toBeTrue()
            ->and($response->toTransactionData())->toMatchArray([
                'tracking_code' => 'TCK-001',
                'provider_id'   => 'PRV-001',
                'amount'        => 150000,
            ]);
    });

    it('handles null optional fields', function (): void {
        $response = VerifyResponse::fromResponse([
            'trackingCode'   => 'TCK-002',
            'providerId'     => 'PRV-002',
            'amount'         => 50000,
            'paymentGateway' => 0,
            'result'         => ['status' => 0, 'message' => 'OK'],
        ]);

        expect($response->rrn)->toBeNull()
            ->and($response->maskedPan)->toBeNull();
    });
});

describe('CallbackPayload', function (): void {
    it('detects successful payment', function (): void {
        $payload = CallbackPayload::fromRequest([
            'amount'       => 10000,
            'providerId'   => 'PRV-001',
            'trackingCode' => 'TCK-001',
            'result'       => 'SUCCESS',
            'type'         => 11,
        ]);

        expect($payload->isSuccessful())->toBeTrue();
    });

    it('detects failed payment', function (): void {
        $payload = CallbackPayload::fromRequest([
            'amount'       => 10000,
            'providerId'   => 'PRV-001',
            'trackingCode' => 'TCK-001',
            'result'       => 'FAILURE',
            'type'         => 0,
        ]);

        expect($payload->isSuccessful())->toBeFalse();
    });

    it('defaults result to FAILURE when missing', function (): void {
        $payload = CallbackPayload::fromRequest([]);

        expect($payload->result)->toBe('FAILURE')
            ->and($payload->isSuccessful())->toBeFalse();
    });
});

describe('RefundResponse', function (): void {
    it('parses successful refund', function (): void {
        $response = RefundResponse::fromResponse([
            'result'       => ['status' => 0, 'message' => 'Refund processed'],
            'trackingCode' => 'RTCK-001',
        ]);

        expect($response->isSuccessful())->toBeTrue()
            ->and($response->statusCode)->toBe(0);
    });

    it('parses failed refund', function (): void {
        $response = RefundResponse::fromResponse([
            'result' => ['status' => 500, 'message' => 'Refund failed'],
        ]);

        expect($response->isSuccessful())->toBeFalse();
    });
});

describe('DeliverResponse', function (): void {
    it('parses successful delivery', function (): void {
        $response = DeliverResponse::fromResponse([
            'result' => ['status' => 0, 'message' => 'Delivered'],
            'level'  => 'INFO',
        ]);

        expect($response->isSuccessful())->toBeTrue();
    });
});

describe('ReverseResponse', function (): void {
    it('parses successful reverse', function (): void {
        $response = ReverseResponse::fromResponse([
            'trackingCode'   => 'TCK-001',
            'amount'         => 10000,
            'paymentGateway' => 1,
            'result'         => ['status' => 0, 'message' => 'Reversed'],
        ]);

        expect($response->trackingCode)->toBe('TCK-001')
            ->and($response->amount)->toBe(10000);
    });
});

describe('RefundInquiryResponse', function (): void {
    it('parses completed refund inquiry', function (): void {
        $response = RefundInquiryResponse::fromResponse([
            'result'       => ['status' => 0, 'message' => 'OK'],
            'status'       => 0,
            'trackingCode' => 'RTCK-001',
        ]);

        expect($response->isSuccessful())->toBeTrue()
            ->and($response->isRefundCompleted())->toBeTrue()
            ->and($response->isRefundFailed())->toBeFalse()
            ->and($response->isRefundPending())->toBeFalse();
    });

    it('detects failed refund inquiry', function (): void {
        $response = RefundInquiryResponse::fromResponse([
            'result' => ['status' => 0, 'message' => 'OK'],
            'status' => 1,
        ]);

        expect($response->isRefundFailed())->toBeTrue()
            ->and($response->isRefundCompleted())->toBeFalse();
    });

    it('detects pending refund inquiry', function (): void {
        $response = RefundInquiryResponse::fromResponse([
            'result' => ['status' => 0, 'message' => 'OK'],
            'status' => 2,
        ]);

        expect($response->isRefundPending())->toBeTrue();
    });
});
