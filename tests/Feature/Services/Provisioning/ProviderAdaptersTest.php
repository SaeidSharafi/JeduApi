<?php

declare(strict_types=1);

use App\Contracts\Integrations\SkyroomClientContract;
use App\Contracts\Integrations\SpotPlayerClientContract;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Services\Integrations\ImsService;
use App\Services\Integrations\MoodleService;
use App\Services\Provisioning\Providers\ImsProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleQuizProvisioningProvider;
use App\Services\Provisioning\Providers\SkyroomProvisioningProvider;
use App\Services\Provisioning\Providers\SpotPlayerProvisioningProvider;
use App\Services\Provisioning\ProvisioningProviderRegistry;

function adapterEnrollment(string $provider, array $details): Enrollment
{
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
        'details_json'    => $details,
    ]);

    $enrollment = Enrollment::factory()->create([
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    $enrollment->update([
        'provisioning_plan' => [
            'version'   => 1,
            'providers' => [['provider' => $provider, 'applicable' => true, 'readiness' => 'ready']],
        ],
    ]);

    return $enrollment->fresh();
}

it('provisions SpotPlayer and returns canonical references', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['spot_id' => 'SPOT-1']);
    $service    = $this->mock(SpotPlayerClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('issueLicense')->with('SPOT-1', Mockery::type(App\Models\User::class))->andReturn([
        'license_key' => 'LIC-1', 'player_url' => 'https://player.test/1',
    ]);

    expect((new SpotPlayerProvisioningProvider($service))->provision($enrollment))->toBe([
        'spot_id' => 'SPOT-1', 'license_key' => 'LIC-1', 'player_url' => 'https://player.test/1',
    ]);
});

it('marks an uncertain SpotPlayer response as manual action', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['spot_id' => 'SPOT-1']);
    $service    = $this->mock(SpotPlayerClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('issueLicense')->andThrow(new RecoverableProvisioningException('timeout', 0, null,
        ['http_status' => 504]));

    expect(fn () => (new SpotPlayerProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'ambiguous');
});

it('rejects a SpotPlayer provider when its content reference is missing', function (): void {
    $enrollment = adapterEnrollment('spotplayer', []);
    $service    = $this->mock(SpotPlayerClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new SpotPlayerProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'spot_id');
});

it('rejects a Moodle Quiz provider that is not in the canonical plan', function (): void {
    $enrollment = adapterEnrollment('spotplayer', ['moodle_quiz_course_id' => 99]);
    $service    = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new MoodleQuizProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'not applicable');
});

it('rejects a Moodle Quiz provider when its course reference is missing', function (): void {
    $enrollment = adapterEnrollment('moodle_quiz', []);
    $service    = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new MoodleQuizProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'course_id');
});

it('rejects an IMS provider when its course reference is missing', function (): void {
    $enrollment = adapterEnrollment('ims', []);
    $service    = $this->mock(ImsService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new ImsProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class, 'course code');
});

it('reports immutable paid value and the complete discount for an IMS enrollment', function (): void {
    $customer = App\Models\User::factory()->create();
    $option   = ProductDeliveryOption::factory()->create([
        'price'        => 100000,
        'details_json' => ['ims_course_code' => 'IMS-PRICE-1'],
    ]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'grand_total' => 60000,
    ]);
    $item = OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $option->id,
        'price'                      => 100000,
        'total'                      => 60000,
        'pricing_metadata'           => [
            'base_price_amount'     => 100000,
            'paid_amount'           => 60000,
            'total_discount_amount' => 40000,
        ],
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_id'                   => $order->id,
        'order_item_id'              => $item->id,
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $option->id,
    ]);
    Payment::factory()->create([
        'order_id'    => $order->id,
        'customer_id' => $customer->id,
        'amount'      => 160000,
        'status'      => App\Enums\Payment\PaymentStatusEnum::COMPLETED,
    ]);

    $service = $this->mock(ImsService::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('storeStudent')->andReturn(['data' => ['student_id' => 7]]);
    $service->shouldReceive('storeEnrollment')->once()->withArgs(function ($user, array $payload): bool {
        return $payload['payment']['amount']          === 60000
            && $payload['payment']['discount_type']   === 'manual'
            && $payload['payment']['discount_amount'] === 40000;
    })->andReturn(['data' => ['enrollment_id' => 9]]);

    expect((new ImsProvisioningProvider($service))->provision($enrollment))
        ->toMatchArray(['course_code' => 'IMS-PRICE-1', 'ims_enrollment_id' => 9]);
});

it('resolves every provisioning provider case to its own adapter', function (): void {
    $registry = app(ProvisioningProviderRegistry::class);

    foreach (ProvisioningProviderEnum::cases() as $provider) {
        expect($registry->resolve($provider)->provider())->toBe($provider);
    }
});

it('provisions Skyroom into a staff-created room', function (): void {
    $enrollment = adapterEnrollment('skyroom', ['room_id' => 10]);
    $service    = $this->mock(SkyroomClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('findOrCreateUser')->once()->andReturn(['skyroom_user_id' => 42]);
    $service->shouldReceive('addUserToRoom')->once()->with(10, 42);

    expect((new SkyroomProvisioningProvider($service))->provision($enrollment))
        ->toBe(['room_id' => 10, 'skyroom_user_id' => 42]);
});

it('rejects a missing staff-created room as manual action', function (): void {
    $enrollment = adapterEnrollment('skyroom', ['room_id' => null]);
    $service    = $this->mock(SkyroomClientContract::class);
    $service->shouldReceive('isEnabled')->andReturnTrue();
    $service->shouldReceive('assertConfigured');

    expect(fn () => (new SkyroomProvisioningProvider($service))->provision($enrollment))
        ->toThrow(UnrecoverableProvisioningException::class);
});

it('revokes Moodle access for suspended lifecycle changes', function (): void {
    $enrollment = adapterEnrollment('moodle', []);
    $enrollment->update([
        'provisioning_data' => [
            'providers' => [
                'moodle' => [
                    'status' => 'success', 'data' => [
                        'moodle_user_id' => 42, 'moodle_course_id' => 99,
                    ],
                ],
            ],
        ],
    ]);
    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('unenrollUser')->once()->with(42, 99);

    expect((new MoodleProvisioningProvider($service))->reconcileAccess($enrollment, [
        'requested_status' => EnrollmentStatusEnum::SUSPENDED->value,
    ]))->toBe(['moodle_user_id' => 42, 'moodle_course_id' => 99]);
});
