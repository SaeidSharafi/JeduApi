<?php

declare(strict_types=1);

use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Jobs\Provisioning\ProvisionImsEnrollmentJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\ImsService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

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

});

it('returns when IMS integration is disabled', function (): void {
    Setting::setValue(SettingKeyEnum::IMS, array_merge($this->config, ['enabled' => false]), 'json', 'integrations');

    $service = $this->mock(ImsService::class);
    $service->shouldReceive('setConfig')->never();
    $service->shouldNotReceive('storeEnrolment');

    $enrollment = createEnrollmentAndPaymentForImsJob()[0];

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->handle($service, app(SettingsService::class));

    $enrollment->refresh();
    expect($enrollment->provisioning_data)->not->toHaveKey('providers.ims');
});

it('throws when IMS configuration is missing base_url or api_key', function (): void {
    Setting::setValue(SettingKeyEnum::IMS, array_merge($this->config, ['base_url' => '']), 'json', 'integrations');

    $service = $this->mock(ImsService::class);
    $job     = new ProvisionImsEnrollmentJob(1);

    expect(fn () => $job->handle($service, app(SettingsService::class)))
        ->toThrow(RuntimeException::class, 'IMS is enabled but configuration is missing.');
});

it('sends configured IMS bank account number in payload', function (): void {
    config([
        'payments.mellat.ims_bank_account_number' => 'IMS-ACC-001',
    ]);

    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['enrollment_id' => 1001],
        ], 200),
    ]);

    [$enrollment, $payment] = createEnrollmentAndPaymentForImsJob();

    $job = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);
    $job->handle(app(ImsService::class), app(SettingsService::class));

    Http::assertSent(function ($request) {
        return $request['registrations'][0]['payment']['bank_account_number'] === 'IMS-ACC-001';
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
    $job->handle(app(ImsService::class), app(SettingsService::class));

    Http::assertSent(function ($request) {
        return array_key_exists('bank_account_number', $request['registrations'][0]['payment'])
            && $request['registrations'][0]['payment']['bank_account_number'] === null;
    });
});

it('returns when enrollment does not exist', function (): void {
    Http::fake();

    $job = new ProvisionImsEnrollmentJob(999999);
    $job->handle(app(ImsService::class), app(SettingsService::class));

    Http::assertNothingSent();
});

it('throws when ims course code is missing', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob(
        paymentOverrides: [],
        deliveryDetailsOverrides: ['ims_course_code' => null],
    );

    $job = new ProvisionImsEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle(app(ImsService::class), app(SettingsService::class)))
        ->toThrow(RuntimeException::class, 'IMS course code is missing from delivery option details.');
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

    expect(fn () => invokeProvisionImsPrivate($job, 'resolvePaymentAmount', $enrollment))
        ->toThrow(RuntimeException::class, 'Completed payment is required for IMS provisioning.');
});

it('throws when enrollment has no order while resolving amount', function (): void {
    $enrollment = new Enrollment();

    $job = new ProvisionImsEnrollmentJob(1);

    expect(fn () => invokeProvisionImsPrivate($job, 'resolvePaymentAmount', $enrollment))
        ->toThrow(RuntimeException::class, 'Enrollment order is required for IMS provisioning.');
});

it('throws when payment order id does not match enrollment order id', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob(createCompletedPayment: false);
    $payment      = Payment::factory()->create([]);
    $job          = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);

    expect(fn () => invokeProvisionImsPrivate($job, 'resolvePaymentOrFail', $enrollment))
        ->toThrow(RuntimeException::class, 'Payment does not belong to enrollment order.');
});

it('throws when payment status is not completed', function (): void {
    [$enrollment, $payment] = createEnrollmentAndPaymentForImsJob();
    $payment->status        = PaymentStatusEnum::PENDING;
    $payment->save();
    $job = new ProvisionImsEnrollmentJob($enrollment->id, $payment->id);

    expect(fn () => invokeProvisionImsPrivate($job, 'resolvePaymentOrFail', $enrollment))
        ->toThrow(RuntimeException::class, 'Payment must be completed before IMS provisioning');
});

it('throws during handle when no completed payment exists and does not call IMS', function (): void {
    Http::fake();

    [$enrollment] = createEnrollmentAndPaymentForImsJob(createCompletedPayment: false);

    $job = new ProvisionImsEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle(app(ImsService::class), app(SettingsService::class)))
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
    $job->handle(app(ImsService::class), app(SettingsService::class));

    Http::assertSent(function ($request) {
        return $request['registrations'][0]['payment']['bill'] === 'TX-777';
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
    $job->handle(app(ImsService::class), app(SettingsService::class));

    $expectedDate = $payment->created_at?->toDateString();

    Http::assertSent(function ($request) use ($expectedDate) {
        return $request['registrations'][0]['payment']['date'] === $expectedDate;
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
    $job->handle(app(ImsService::class), app(SettingsService::class));

    Http::assertSent(function ($request) {
        return $request['registrations'][0]['payment']['bank_account_number'] === 'IMS-ACC-BANK';
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
    $job->handle(app(ImsService::class), app(SettingsService::class));

    Http::assertSent(function ($request) {
        return $request['registrations'][0]['payment']['bank_account_number'] === 'IMS-ACC-WALLET';
    });
});

it('marks provisioning failure on failed callback', function (): void {
    [$enrollment] = createEnrollmentAndPaymentForImsJob();

    $job = new ProvisionImsEnrollmentJob($enrollment->id);
    $job->failed(new RuntimeException('ims failed hard'));

    $enrollment->refresh();

    expect($enrollment->enrollment_status)->toBe(App\Enums\EnrollmentStatusEnum::PROVISIONING_FAILED)
        ->and(data_get($enrollment->provisioning_data, 'providers.ims.status'))->toBe('failed')
        ->and(data_get($enrollment->provisioning_data, 'providers.ims.last_error'))->toBe('ims failed hard');
});

it('returns from failed callback when enrollment does not exist', function (): void {
    $job = new ProvisionImsEnrollmentJob(999999);

    $job->failed(new RuntimeException('ims failed hard'));

    // No exception thrown; no enrollment to mutate — assert job completes cleanly
    expect(Enrollment::find(999999))->toBeNull();
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
