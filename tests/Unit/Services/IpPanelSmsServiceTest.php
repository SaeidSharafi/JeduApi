<?php

declare(strict_types=1);

use App\Models\SmsLog;
use App\Services\IpPanelSmsService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Test setup common to both test groups
beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.ippanel.api_key'  => 'test-api-key',
        'services.ippanel.from'     => '1000',
        'services.ippanel.sand_box' => false,
    ]);
    $this->service = app(IpPanelSmsService::class);
});

describe('Normal SMS Sending', function () {
    it('sends sms successfully and creates a log', function () {
        $to = ['09123456789'];
        $message = 'Test message';
        Http::fake([
            'api2.ippanel.com/*' => Http::response(['data' => ['message_id' => 'fake-id']], 200),
        ]);

        $this->service->send($to, $message);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->content)->toBe($message)
            ->and($smsLog->to)->toBe($to);
    });

    // We can group error tests for conciseness
    it('throws exception on client and server errors and still creates a log', function (int $statusCode, ?array $body) {
        Http::fake([
            'api2.ippanel.com/*' => Http::response($body, $statusCode),
        ]);
        Log::shouldReceive('error')->once(); // We expect an error to be logged

        expect(fn () => $this->service->send(['09123456789'], 'Test'))
            ->toThrow(RequestException::class);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe($statusCode)
            ->and($smsLog->data)->toBe($body);

    })->with([
        '400 Bad Request' => [400, null],
        '401 Unauthorized' => [401, null],
        '403 Forbidden' => [403, ['errorMessage' => 'Forbidden']],
        '422 Unprocessable' => [422, ['errors' => ['field' => 'invalid']]],
        '500 Server Error' => [500, null],
    ]);

    it('handles sandbox mode correctly', function () {
        config(['services.ippanel.sand_box' => true]);
        Http::fake(); // Assert that NO http requests are sent

        $this->service->send(['09123456789'], 'Test message');

        Http::assertNothingSent();
        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->data['message_id'])->toBeString();
    });

    it('throws exception if api key or from is not set', function () {
        // Arrange
        config([
            'services.ippanel.api_key' => null,
            'services.ippanel.from'    => null,
        ]);
        $serviceWithoutConfig = app(IpPanelSmsService::class);

        // Act & Assert
        expect(fn () => $serviceWithoutConfig->send(['09123456789'], 'Test'))
            ->toThrow(Exception::class, 'IPPanel API key or sender number is not configured.');
    });

    it('allows overriding config values with setters', function () {
        config([
            'services.ippanel.api_key' => 'config-key',
            'services.ippanel.from'    => '1111',
        ]);

        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $service = app(App\Services\IpPanelSmsService::class);

        $service->setApiKey('runtime-key');
        $service->setFrom('9999');
        $service->send(['09123456789'], 'Test message');

        Http::assertSent(function ($request) {
            $headerIsCorrect = $request->hasHeader('apikey', 'runtime-key');
            $senderIsCorrect = $request['sender'] === '9999';

            return $headerIsCorrect && $senderIsCorrect;
        });
    });
});


describe('Pattern SMS Sending', function () {
    it('sends pattern sms successfully and creates a log', function () {
        Http::fake([
            'api2.ippanel.com/*' => Http::response(['data' => ['message_id' => 'fake-id']], 200),
        ]);

        $this->service->sendPattern('pattern-code', ['code' => '123'], '09123456789');

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->type)->toBe('pattern');
    });

    it('throws exception on client and server errors for patterns and still logs', function (int $statusCode, ?array $body) {
        Http::fake([
            'api2.ippanel.com/*' => Http::response($body, $statusCode),
        ]);
        Log::shouldReceive('error')->once();

        // Act & Assert
        expect(fn () => $this->service->sendPattern('pattern-code', ['code' => '123'], '09123456789'))
            ->toThrow(RequestException::class);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe($statusCode);

    })->with([
        '401 Unauthorized' => [401, null],
        '422 Unprocessable' => [422, ['errors' => ['field' => 'invalid']]],
        '500 Server Error' => [500, null],
    ]);
});
