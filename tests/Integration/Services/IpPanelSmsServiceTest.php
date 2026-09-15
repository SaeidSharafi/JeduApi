<?php

declare(strict_types=1);

use App\Enums\Sms\SmsSkipReasonEnum;
use App\Enums\System\SettingKeyEnum;
use App\Models\Setting;
use App\Models\SmsLog;
use App\Services\IpPanelSmsService;
use App\Services\SettingsService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

covers(IpPanelSmsService::class);

/*
|--------------------------------------------------------------------------
| Mutation notes
|--------------------------------------------------------------------------
|
| Survivors left after `pest --mutate` for this file, and why:
|
| - `'Sandbox_'.randomNumber(10)` (IncrementInteger / DecrementInteger /
|   ConcatRemoveRight on the free-text and pattern sandbox branches): the
|   sandbox message id is deliberately random, and the tests assert the
|   `Sandbox_` prefix and that the id is present. Pinning the value would test
|   the mutator, not the send contract.
| - The `?? false` / `?? ''` fallbacks in `resolveGatewaySettings()`: every
|   field is declared by `config/sms.php` and merged in by
|   `SmsGatewayEnum::resolvedSettings()`, so the fallbacks are unreachable and
|   the mutants are equivalent.
| - The `(string)` cast on the sender in `senderFrom()`: `sms_logs.from` is a
|   string column, so an integer override is stored and read back as a string
|   either way. The `?? $gateway['from']` half of that expression is covered by
|   the sender-override test.
|
*/

// Test setup common to both test groups
beforeEach(function (): void {
    Http::preventStrayRequests();

    config([
        'sms.gateways.ippanel.enabled' => true,
        'sms.gateways.ippanel.api_key' => 'test-api-key',
        'sms.gateways.ippanel.from'    => '1000',
        'sms.gateways.ippanel.sandbox' => false,
    ]);
    $this->service = app(IpPanelSmsService::class);
});

describe('Normal SMS Sending', function (): void {
    it('sends sms successfully and creates a log', function (): void {
        $to      = ['09123456789'];
        $message = 'Test message';
        Http::fake([
            'api2.ippanel.com/*' => Http::response(['data' => ['message_id' => 'fake-id']], 200),
        ]);

        $this->service->send($to, $message);

        Http::assertSent(function (Request $request) use ($to, $message): bool {
            return $request->url() === 'https://api2.ippanel.com/api/v1/sms/send/webservice/single'
                && $request->hasHeader('apikey', 'test-api-key')
                && $request->data() === [
                    'sender'    => '1000',
                    'recipient' => $to,
                    'message'   => $message,
                ];
        });

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->content)->toBe($message)
            ->and($smsLog->to)->toBe($to);
    });

    // We can group error tests for conciseness
    it('throws exception on client and server errors and still creates a log', function (int $statusCode, ?array $body): void {
        Http::fake([
            'api2.ippanel.com/*' => Http::response($body, $statusCode),
        ]);
        Log::shouldReceive('error')->once()->with(
            'SMS sending failed',
            Mockery::on(fn (array $context): bool => $context['status'] === $statusCode
                && $context['message']                                  === $body
                && $context['to']                                       === '09123456789'
                && $context['from']                                     === '1000'),
        );

        expect(fn () => $this->service->send(['09123456789'], 'Test'))
            ->toThrow(RequestException::class);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe($statusCode)
            ->and($smsLog->data)->toBe($body);

    })->with([
        '400 Bad Request'   => [400, null],
        '401 Unauthorized'  => [401, null],
        '403 Forbidden'     => [403, ['errorMessage' => 'Forbidden']],
        '422 Unprocessable' => [422, ['errors' => ['field' => 'invalid']]],
        '500 Server Error'  => [500, null],
    ]);

    it('handles sandbox mode correctly', function (): void {
        config(['sms.gateways.ippanel.sandbox' => true]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->send(['09123456789'], 'Test message');

        Http::assertNothingSent();
        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->data['message_id'])->toBeString();
    });

    it('allows overriding config values with setters', function (): void {
        config([
            'sms.gateways.ippanel.api_key' => 'config-key',
            'sms.gateways.ippanel.from'    => '1111',
        ]);

        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $service = app(IpPanelSmsService::class);

        $service->setApiKey('runtime-key');
        $service->setFrom(9999);
        $service->send(['09123456789'], 'Test message');

        Http::assertSent(function (Request $request): bool {
            $headerIsCorrect = $request->hasHeader('apikey', 'runtime-key');
            $senderIsCorrect = $request->data()['sender'] === '9999';

            return $headerIsCorrect && $senderIsCorrect;
        });
    });
});

describe('Gateway settings', function (): void {
    it('uses the api key and sender number saved through the settings service', function (): void {
        config([
            'sms.gateways.ippanel.api_key' => 'config-key',
            'sms.gateways.ippanel.from'    => '9999',
        ]);
        app(SettingsService::class)->set(SettingKeyEnum::SMS_IPPANEL, [
            'enabled' => true,
            'label'   => 'IPPanel',
            'from'    => '2222',
            'api_key' => 'stored-key',
            'sandbox' => false,
        ], 'json', 'sms');
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        app(IpPanelSmsService::class)->send(['09123456789'], 'Test message');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('apikey', 'stored-key')
            && $request->data()['sender'] === '2222');
    });

    it('uses a gateway key saved after the service was built on the next send', function (): void {
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->send(['09123456789'], 'Before rotation');

        app(SettingsService::class)->set(SettingKeyEnum::SMS_IPPANEL, [
            'enabled' => true,
            'label'   => 'IPPanel',
            'from'    => '2222',
            'api_key' => 'rotated-key',
            'sandbox' => false,
        ], 'json', 'sms');

        $this->service->send(['09123456789'], 'After rotation');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('apikey', 'rotated-key')
            && $request->data()['sender'] === '2222');
    });

    it('blocks the send and records a skipped attempt when the gateway is switched off', function (): void {
        config(['sms.gateways.ippanel.enabled' => false]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->send(['09123456789'], 'Test message');

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'gateway_disabled'])
            ->and($smsLog->content)->toBe('Test message')
            ->and($smsLog->type)->toBe('custom')
            ->and($smsLog->to)->toBe(['09123456789'])
            ->and($smsLog->from)->toBe('1000');
    });

    it('blocks the send when the stored gateway is switched off', function (): void {
        Setting::factory()->smsIppanel()->create([
            'value' => [
                'enabled' => false,
                'label'   => 'IPPanel',
                'from'    => '1000',
                'api_key' => Crypt::encryptString('stored-key'),
                'sandbox' => false,
            ],
        ]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->send(['09123456789'], 'Test message');

        Http::assertNothingSent();

        expect(SmsLog::latest()->first()->data)->toBe(['reason' => 'gateway_disabled']);
    });

    it('does not attempt a send and records it when the gateway has no api key', function (): void {
        config(['sms.gateways.ippanel.api_key' => null]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->send(['09123456789'], 'Test message');

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'not_configured'])
            ->and($smsLog->content)->toBe('Test message');
    });

    it('does not attempt a send and records it when the gateway has no sender number', function (): void {
        config(['sms.gateways.ippanel.from' => '']);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->send(['09123456789'], 'Test message');

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'not_configured'])
            ->and($smsLog->from)->toBe('');
    });

    it('does not attempt a send when the stored api key is empty', function (): void {
        config(['sms.gateways.ippanel.api_key' => 'config-key']);
        Setting::factory()->smsIppanel()->create([
            'value' => [
                'enabled' => true,
                'label'   => 'IPPanel',
                'from'    => '1000',
                'api_key' => '',
                'sandbox' => false,
            ],
        ]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->send(['09123456789'], 'Test message');

        Http::assertNothingSent();

        expect(SmsLog::latest()->first()->data)->toBe(['reason' => 'not_configured']);
    });

    it('reads the sandbox flag from the stored gateway', function (): void {
        config(['sms.gateways.ippanel.sandbox' => false]);
        Setting::factory()->smsIppanel()->create([
            'value' => [
                'enabled' => true,
                'label'   => 'IPPanel',
                'from'    => '1000',
                'api_key' => Crypt::encryptString('stored-key'),
                'sandbox' => true,
            ],
        ]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->send(['09123456789'], 'Test message');

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->data['message_id'])->toStartWith('Sandbox_');
    });

    it('records an option skip the notification gate requested without calling the provider', function (): void {
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->recordSkipped('09123456789', 'content', 'OTP', SmsSkipReasonEnum::OPTION_DISABLED);

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'option_disabled'])
            ->and($smsLog->content)->toBe('content')
            ->and($smsLog->type)->toBe('OTP')
            ->and($smsLog->to)->toBe('09123456789')
            ->and($smsLog->from)->toBe('1000');
    });

    it('records an option skip with the sender override when one was set', function (): void {
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->setFrom(9999);
        $this->service->recordSkipped('09123456789', 'content', 'OTP', SmsSkipReasonEnum::OPTION_DISABLED);

        expect(SmsLog::latest()->first()->from)->toBe('9999');
    });
});

describe('Pattern SMS Sending', function (): void {
    it('sends pattern sms successfully and creates a log', function (): void {
        Http::fake([
            'api2.ippanel.com/*' => Http::response(['data' => ['message_id' => 'fake-id']], 200),
        ]);

        $this->service->sendPattern('pattern-code', ['code' => '123'], '09123456789');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send'
                && $request->hasHeader('apikey', 'test-api-key')
                && $request->data() === [
                    'code'      => 'pattern-code',
                    'sender'    => '1000',
                    'recipient' => '09123456789',
                    'variable'  => ['code' => '123'],
                ];
        });

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->type)->toBe('pattern')
            ->and($smsLog->content)->toBe('');
    });

    it('throws exception on client and server errors for patterns and still logs', function (int $statusCode, ?array $body): void {
        Http::fake([
            'api2.ippanel.com/*' => Http::response($body, $statusCode),
        ]);
        Log::shouldReceive('error')->once()->with(
            'SMS sending failed',
            Mockery::on(fn (array $context): bool => $context['pattern'] === 'pattern-code'
                && $context['type']                                      === 'pattern'
                && $context['status']                                    === $statusCode
                && $context['message']                                   === $body
                && $context['to']                                        === '09123456789'
                && $context['from']                                      === '1000'),
        );

        // Act & Assert
        expect(fn () => $this->service->sendPattern('pattern-code', ['code' => '123'], '09123456789'))
            ->toThrow(RequestException::class);

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe($statusCode);

    })->with([
        '401 Unauthorized'  => [401, null],
        '422 Unprocessable' => [422, ['errors' => ['field' => 'invalid']]],
        '500 Server Error'  => [500, null],
    ]);

    it('blocks pattern sends and records a skipped attempt when the gateway is switched off', function (): void {
        config(['sms.gateways.ippanel.enabled' => false]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->sendPattern('pattern-code', ['code' => '123'], '09123456789', 'content', 'OTP');

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(SmsLog::STATUS_SKIPPED)
            ->and($smsLog->data)->toBe(['reason' => 'gateway_disabled'])
            ->and($smsLog->to)->toBe('09123456789')
            ->and($smsLog->type)->toBe('OTP');
    });

    it('simulates pattern sends in sandbox mode', function (): void {
        config(['sms.gateways.ippanel.sandbox' => true]);
        Http::fake(['api2.ippanel.com/*' => Http::response([], 200)]);

        $this->service->sendPattern('pattern-code', ['code' => '123'], '09123456789', 'content', 'OTP');

        Http::assertNothingSent();

        $smsLog = SmsLog::latest()->first();
        expect($smsLog->status)->toBe(200)
            ->and($smsLog->data)->toMatchArray([
                'pattern'    => 'pattern-code',
                'parameters' => ['code' => '123'],
            ])
            ->and($smsLog->data['message_id'])->toStartWith('Sandbox_');
    });
});
