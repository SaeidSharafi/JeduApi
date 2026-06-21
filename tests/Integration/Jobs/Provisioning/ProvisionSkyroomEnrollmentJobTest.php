<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Jobs\Provisioning\ProvisionSkyroomEnrollmentJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\SkyroomService;

beforeEach(function () {
    $this->config = [
        'enabled'    => true,
        'service_id' => 'test-service-123',
        'api_key'    => 'skyroom-api-key-abc',
    ];
    Setting::setValue(SettingKeyEnum::SKYROOM, $this->config, 'json', 'integrations');
});

it('returns when skyroom integration is disabled', function (): void {
    Setting::setValue(SettingKeyEnum::SKYROOM, array_merge($this->config, ['enabled' => false]), 'json', 'integrations');

    $service = $this->mock(SkyroomService::class);
    $service->shouldReceive('isEnabled')->andReturn(false);
    $service->shouldNotReceive('findOrCreateUser');
    $service->shouldNotReceive('addUserToRoom');

    $enrollment = createSkyroomEnrollmentForJob();

    $job = new ProvisionSkyroomEnrollmentJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();
    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PENDING_PROVISIONING)
        ->and($enrollment->provisioning_data)->not->toHaveKey('providers.skyroom');
});

it('throws when skyroom configuration is missing api_key', function (): void {
    Setting::setValue(SettingKeyEnum::SKYROOM, array_merge($this->config, ['api_key' => '']), 'json', 'integrations');

    $job = new ProvisionSkyroomEnrollmentJob(1);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'SkyroomService configuration is missing or invalid.');
});

it('returns when enrollment does not exist', function (): void {
    $service = $this->mock(SkyroomService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldNotReceive('findOrCreateUser');

    $job = new ProvisionSkyroomEnrollmentJob(999999);
    $job->handle();

    expect(Enrollment::find(999999))->toBeNull();
});

it('throws when room_id is not numeric', function (): void {
    $enrollment = createSkyroomEnrollmentForJob([
        'room_id' => 'not-int',
    ]);

    $service = $this->mock(SkyroomService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');

    $job = new ProvisionSkyroomEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'Skyroom room_id is missing from delivery option details.');
});

it('provisions skyroom enrollment', function (): void {
    $enrollment = createSkyroomEnrollmentForJob([
        'room_id' => 789,
    ]);

    $service = $this->mock(SkyroomService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('findOrCreateUser')
        ->once()
        ->with(
            Mockery::on(fn ($user): bool => $user instanceof User && $user->is($enrollment->customer))
        )
        ->andReturn(['skyroom_user_id' => 555]);
    $service->shouldReceive('addUserToRoom')
        ->once()
        ->with(789, 555);

    $job = new ProvisionSkyroomEnrollmentJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.skyroom.status'))->toBe('success')
        ->and(data_get($enrollment->provisioning_data, 'providers.skyroom.data.room_id'))->toBe(789)
        ->and(data_get($enrollment->provisioning_data, 'providers.skyroom.data.skyroom_user_id'))->toBe(555);
});

it('marks provisioning failure on failed callback', function (): void {
    $enrollment = createSkyroomEnrollmentForJob();

    $job = new ProvisionSkyroomEnrollmentJob($enrollment->id);
    $job->failed(new RuntimeException('skyroom failed'));

    $enrollment->refresh();

    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PROVISIONING_FAILED)
        ->and(data_get($enrollment->provisioning_data, 'providers.skyroom.status'))->toBe('failed')
        ->and(data_get($enrollment->provisioning_data, 'providers.skyroom.last_error'))->toBe('skyroom failed');
});

it('returns from failed callback when enrollment does not exist', function (): void {
    $job = new ProvisionSkyroomEnrollmentJob(999999);

    $job->failed(new RuntimeException('skyroom failed'));

    expect(Enrollment::find(999999))->toBeNull();
});

it('returns configured backoff values', function (): void {
    $job = new ProvisionSkyroomEnrollmentJob(1);

    expect($job->backoff())->toBe([60, 180, 600]);
});

// ─── Helper ───────────────────────────────────────────────────────────────────

function createSkyroomEnrollmentForJob(array $detailsOverrides = []): Enrollment
{
    $customer = User::factory()->create();
    $order    = Order::factory()->for($customer, 'customer')->create();

    $details = array_merge([
        'room_id' => 100,
    ], $detailsOverrides);

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::LIVE_SESSION_SKYROOM,
        'details_json'    => $details,
    ]);

    $item = OrderItem::factory()
        ->for($order)
        ->for($deliveryOption, 'productDeliveryOption')
        ->create();

    return Enrollment::factory()->for($item)->create([
        'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING,
    ]);
}
