<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Models\AdminActionLog;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\ImsService;
use App\Services\SettingsService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->config = [
        'enabled'              => true,
        'base_url'             => 'https://ims.test',
        'enrollments_endpoint' => '/api/v1/enrol',
        'api_key'              => 'ims-test-key',
        'api_key_header'       => 'X-API-KEY',
        'timeout'              => 15,
    ];
    Setting::setValue(SettingKeyEnum::IMS, $this->config, 'json', 'integrations');
    // ImsService reads config via SettingsService::get(), need mock
    $settings = $this->mock(SettingsService::class);
    $settings->shouldReceive('get')
        ->withAnyArgs()
        ->andReturnUsing(fn (): array => $this->config);
});

it('returns when IMS integration is disabled', function (): void {
    $this->config['enabled'] = false;

    $service = $this->mock(ImsService::class);
    $service->shouldReceive('isEnabled')->andReturn(false);
    $service->shouldNotReceive('storeSetudent');
    $service->shouldNotReceive('storeEnrollment');

    $enrollment = createEnrollmentAndPaymentForImsJob()[0];

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();
    expect($enrollment->provisioning_data)->not->toHaveKey('providers.ims');
});

it('throws when IMS configuration is missing base_url or api_key', function (): void {
    $this->config['base_url'] = '';

    $job = new ProvisionImsEnrollmentJob(1);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'ImsService configuration is missing or invalid.');
});

it('sends configured IMS bank account number in payload', function (): void {
    config([
        'payments.mellat.ims_bank_account_number' => 'IMS-ACC-001',
    ]);

    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['enrollment_id' => '1001'],
        ], 200),
    ]);

    [$enrollment, $payment] = createEnrollmentAndPaymentForImsJob();

    $job = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);
    $job->handle();

    expect($enrollment->refresh()->external_enrollment_id)->toBe(1001)
        ->and(data_get($enrollment->provisioning_data, 'providers.ims.data.enrollment_id'))->toBe(1001);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/v2/enrollment/')
            && ($request->data()['payment']['bank_account_number'] ?? null) === 'IMS-ACC-001';
    });
});

it('sends null IMS bank account number when gateway config is missing', function (): void {
    config([
        'payments.mellat.ims_bank_account_number' => null,
    ]);

    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['enrollment_id' => 1002],
        ], 200),
    ]);

    [$enrollment, $payment] = createEnrollmentAndPaymentForImsJob([
        'data' => [
            'bank_account_number' => 'LEGACY-SHOULD-NOT-BE-USED',
            'transaction_date'    => '2026-02-10',
        ],
    ]);

    $job = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);
    $job->handle();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/v2/enrollment/')
            && array_key_exists('bank_account_number', $request->data()['payment'])
            && $request->data()['payment']['bank_account_number'] === null;
    });
});

it('returns when enrollment does not exist', function (): void {
    Http::fake();

    $job = new ProvisionImsEnrollmentJob(999999);
    $job->handle();

    Http::assertNothingSent();
});

it('throws when ims course code is missing', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob(
        paymentOverrides: [],
        deliveryDetailsOverrides: ['ims_course_code' => null],
    );

    $job = new ProvisionImsEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'IMS course code is missing from delivery option details.');
});

it('resolves payment amount from latest completed payment', function (): void {
    config([
        'payments.mellat.ims_bank_account_number' => 'IMS-ACC-MELLAT',
    ]);

    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['enrollment_id' => 2001],
        ], 200),
    ]);

    [$enrollment] = createEnrollmentAndPaymentForImsJob(
        paymentOverrides: ['amount' => 123456],
    );

    $job = new ProvisionImsEnrollmentJob($enrollment->id);

    $resolvedAmount = invokeProvisionImsPrivate($job, 'resolvePaymentAmount', $enrollment);

    expect($resolvedAmount)->toBe(123456);
});

it('throws when no completed payment exists while resolving amount', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob(
        orderOverrides: ['grand_total' => 654321],
        createCompletedPayment: false,
    );

    $job = new ProvisionImsEnrollmentJob($enrollment->id);

    expect(fn (): mixed => invokeProvisionImsPrivate($job, 'resolvePaymentAmount', $enrollment))
        ->toThrow(RuntimeException::class, 'Completed payment is required for IMS provisioning.');
});

it('throws when enrollment has no order while resolving amount', function (): void {
    $enrollment = new Enrollment();

    $job = new ProvisionImsEnrollmentJob(1);

    expect(fn (): mixed => invokeProvisionImsPrivate($job, 'resolvePaymentAmount', $enrollment))
        ->toThrow(RuntimeException::class, 'Enrollment order is required for IMS provisioning.');
});

it('throws when payment order id does not match enrollment order id', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob(createCompletedPayment: false);
    $payment      = Payment::factory()->create([]);
    $job          = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);

    expect(fn (): mixed => invokeProvisionImsPrivate($job, 'resolvePaymentOrFail', $enrollment))
        ->toThrow(RuntimeException::class, 'Payment does not belong to enrollment order.');
});

it('throws when payment status is not completed', function (): void {
    [$enrollment, $payment] = createEnrollmentAndPaymentForImsJob();
    $payment->status        = PaymentStatusEnum::PENDING;
    $payment->save();
    $job = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);

    expect(fn (): mixed => invokeProvisionImsPrivate($job, 'resolvePaymentOrFail', $enrollment))
        ->toThrow(RuntimeException::class, 'Payment must be completed before IMS provisioning');
});

it('throws during handle when no completed payment exists and does not call IMS', function (): void {
    Http::fake();

    [$enrollment] = createEnrollmentAndPaymentForImsJob(createCompletedPayment: false);

    $job = new ProvisionImsEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'Completed payment is required for IMS provisioning.');

    Http::assertNothingSent();
});

it('resolves payment date to null when no payment exists', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob(createCompletedPayment: false);

    $job          = new ProvisionImsEnrollmentJob($enrollment->id);
    $resolvedDate = invokeProvisionImsPrivate($job, 'resolvePaymentDate', $enrollment);

    expect($resolvedDate)->toBeNull();
});

it('resolves ims bank account number to null when no payment exists', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob(createCompletedPayment: false);

    $job               = new ProvisionImsEnrollmentJob($enrollment->id);
    $bankAccountNumber = invokeProvisionImsPrivate($job, 'resolveImsBankAccountNumber', $enrollment);

    expect($bankAccountNumber)->toBeNull();
});

it('uses explicit payment id to resolve bill from transaction id fallback', function (): void {
    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['enrollment_id' => 2003],
        ], 200),
    ]);

    [$enrollment, $payment] = createEnrollmentAndPaymentForImsJob([
        'last_gateway_reference' => null,
        'data'                   => [
            'transaction_id'   => 'TX-777',
            'transaction_date' => '2026-02-11',
        ],
    ]);

    $job = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);
    $job->handle();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/v2/enrollment/')
            && ($request->data()['payment']['tracking_code'] ?? null) === 'TX-777';
    });
});

it('falls back to payment created at when transaction date is invalid', function (): void {
    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['enrollment_id' => 2004],
        ], 200),
    ]);

    [$enrollment, $payment] = createEnrollmentAndPaymentForImsJob([
        'created_at' => now()->subDays(3),
        'data'       => [
            'transaction_date' => 'invalid-date',
        ],
    ]);

    $job = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);
    $job->handle();

    $expectedDate = $payment->created_at?->toDateString();

    Http::assertSent(function (Request $request) use ($expectedDate): bool {
        return str_contains($request->url(), '/api/v2/enrollment/')
            && ($request->data()['payment']['date'] ?? null) === $expectedDate;
    });
});

it('sends bank transfer ims account number when payment method is bank transfer', function (): void {
    config([
        'payments.bank_transfer.ims_bank_account_number' => 'IMS-ACC-BANK',
    ]);

    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['enrollment_id' => 2005],
        ], 200),
    ]);

    [$enrollment, $payment] = createEnrollmentAndPaymentForImsJob([
        'method' => PaymentMethodEnum::BANK_TRANSFER->value,
    ]);

    $job = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);
    $job->handle();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/v2/enrollment/')
            && ($request->data()['payment']['bank_account_number'] ?? null) === 'IMS-ACC-BANK';
    });
});

it('sends wallet ims account number when payment method is wallet', function (): void {
    config([
        'payments.wallet.ims_bank_account_number' => 'IMS-ACC-WALLET',
    ]);

    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['enrollment_id' => 2006],
        ], 200),
    ]);

    [$enrollment, $payment] = createEnrollmentAndPaymentForImsJob([
        'method' => PaymentMethodEnum::WALLET->value,
    ]);

    $job = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);
    $job->handle();

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/v2/enrollment/')
            && ($request->data()['payment']['bank_account_number'] ?? null) === 'IMS-ACC-WALLET';
    });
});

it('marks provisioning failure on failed callback', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob();

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->failed(new RuntimeException('ims failed hard'));

    $enrollment->refresh();

    expect($enrollment->enrollment_status)->toBe(App\Enums\EnrollmentStatusEnum::PROVISIONING_FAILED)
        ->and(data_get($enrollment->provisioning_data, 'providers.ims.status'))->toBe('failed')
        ->and(data_get($enrollment->provisioning_data, 'providers.ims.last_error'))->toBe('ims failed hard')
        ->and(data_get($enrollment->provisioning_data, 'providers.ims.metadata'))->toBeArray();
});

it('returns from failed callback when enrollment does not exist', function (): void {
    $job = new ProvisionImsEnrollmentJob(999999);

    $job->failed(new RuntimeException('ims failed hard'));

    // No exception thrown; no enrollment to mutate — assert job completes cleanly
    expect(Enrollment::find(999999))->toBeNull();
});

it('stores ims metadata on provisioning failure', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob();

    $exception = new RecoverableProvisioningException('ims validation failed', 0, null, [
        'http_status'       => 422,
        'endpoint'          => '/api/v2/student',
        'validation_errors' => ['field' => ['error']],
        'raw_body_snippet'  => '{"errors":{"field":["error"]}}',
    ]);

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->failed($exception);

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.ims.metadata.http_status'))->toBe(422);
    expect(data_get($enrollment->provisioning_data, 'providers.ims.metadata.endpoint'))->toBe('/api/v2/student');
    expect(data_get($enrollment->provisioning_data, 'providers.ims.metadata.validation_errors'))->toBeArray();
    expect(data_get($enrollment->provisioning_data, 'providers.ims.status'))->toBe('failed');
});

it('creates AdminActionLog entry on ims provisioning failure', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob();

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->failed(new RuntimeException('ims failed'));

    $log = AdminActionLog::where('action_type', 'ims_provisioning_failed')
        ->where('resource_id', $enrollment->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->admin_id)->toBeNull();
    expect($log->route_name)->toBe('system:ims_provisioning');
    expect($log->http_method)->toBe('QUEUE');
    expect($log->ip_address)->toBe('127.0.0.1');
    expect($log->risk_level)->toBe('high');
});

it('AdminActionLog error_message is static generic not raw exception text', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob();

    $exception = new RecoverableProvisioningException('user submitted PII data: test@example.com', 0, null, [
        'http_status'       => 422,
        'endpoint'          => '/api/v2/student',
        'validation_errors' => [],
        'raw_body_snippet'  => '{"errors":{"national_code":["exists"]}}',
    ]);

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->failed($exception);

    $log = AdminActionLog::where('action_type', 'ims_provisioning_failed')
        ->where('resource_id', $enrollment->id)
        ->first();

    expect(data_get($log->metadata, 'error_message'))->toBe('IMS validation failed');
    expect(data_get($log->metadata, 'error_message'))->not->toContain('test@example.com');
});

it('stores raw_body_snippet in AdminActionLog when exception has metadata', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob();

    $exception = new RecoverableProvisioningException('error', 0, null, [
        'http_status'       => 422,
        'endpoint'          => '/api/v2/student',
        'validation_errors' => [],
        'raw_body_snippet'  => '{"errors":{"field":["msg"]}}',
    ]);

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->failed($exception);

    $log = AdminActionLog::where('action_type', 'ims_provisioning_failed')
        ->where('resource_id', $enrollment->id)
        ->first();

    expect(data_get($log->metadata, 'raw_body_snippet'))->toBe('{"errors":{"field":["msg"]}}');
});

it('sets raw_body_snippet to null in AdminActionLog when plain RuntimeException', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob();

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->failed(new RuntimeException('generic failure'));

    $log = AdminActionLog::where('action_type', 'ims_provisioning_failed')
        ->where('resource_id', $enrollment->id)
        ->first();

    expect(data_get($log->metadata, 'raw_body_snippet'))->toBeNull();
});

it('logs error with enrollment context on failure', function (): void {
    Log::spy();

    [$enrollment] = createEnrollmentAndPaymentForImsJob();

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->failed(new RuntimeException('test error'));

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message, $context): bool => $message === 'IMS provisioning failed'
            && ($context['enrollment_id'] ?? null)           === $enrollment->id
        );
});

it('stores sanitized raw_body_snippet in provisioning_data metadata from ExternalProvisioningException', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob();

    $exception = new RecoverableProvisioningException('error', 0, null, [
        'http_status'       => 422,
        'endpoint'          => '/api/v2/student',
        'validation_errors' => [],
        'raw_body_snippet'  => '{"errors":{"field":["msg"]}}',
    ]);

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->failed($exception);

    $enrollment->refresh();
    $snippet = data_get($enrollment->provisioning_data, 'providers.ims.metadata.raw_body_snippet');
    expect($snippet)->toBe('{"errors":{"field":["msg"]}}');
});

it('returns configured backoff values', function (): void {
    $job = new ProvisionImsEnrollmentJob(1);

    expect($job->backoff())->toBe([60, 180, 600]);
});

function createEnrollmentAndPaymentForImsJob(
    array $paymentOverrides = [],
    array $deliveryDetailsOverrides = [],
    array $orderItemOverrides = [],
    array $orderOverrides = [],
    bool $createCompletedPayment = true,
): array {
    $customer = User::factory()->create();

    $order = Order::factory()->for($customer, 'customer')->create(array_merge([
        'status'      => 'completed',
        'grand_total' => 120000,
    ], $orderOverrides));

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::IN_PERSON,
        'details_json'    => array_merge([
            'ims_course_code' => 'IMS-COURSE-100',
        ], $deliveryDetailsOverrides),
    ]);

    $item = OrderItem::factory()->for($order)->for($deliveryOption, 'productDeliveryOption')->create(array_merge([
        'discount_amount' => 5000,
        'total'           => 95000,
    ], $orderItemOverrides));

    $enrollment = Enrollment::factory()->for($item)->create([
        'notes' => 'online registration',
    ]);

    $payment = null;
    if ($createCompletedPayment) {
        $payment = Payment::factory()->for($order)->for($customer, 'customer')->create(array_merge([
            'status'                 => 'completed',
            'method'                 => PaymentMethodEnum::MELLAT_GATEWAY->value,
            'amount'                 => 95000,
            'last_gateway_reference' => 'REF-123456',
            'data'                   => ['transaction_date' => '2026-02-10'],
        ], $paymentOverrides));
    }

    return [$enrollment, $payment];
}

function invokeProvisionImsPrivate(ProvisionImsEnrollmentJob $job, string $methodName, Enrollment $enrollment): mixed
{
    $method = new ReflectionMethod(ProvisionImsEnrollmentJob::class, $methodName);
    $method->setAccessible(true);

    return $method->invoke($job, $enrollment);
}
