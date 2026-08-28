<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\BbbService;

beforeEach(function (): void {
    $this->config = [
        'enabled'                    => true,
        'base_url'                   => 'https://bbb.test',
        'secret'                     => 'supersecret',
        'api_path'                   => '/bigbluebutton/api',
        'default_attendee_password'  => 'ap-default',
        'default_moderator_password' => 'mp-default',
    ];
    Setting::setValue(SettingKeyEnum::BIG_BLUE_BUTTON, $this->config, 'json', 'integrations');
});

it('returns when bbb integration is disabled', function (): void {
    Setting::setValue(SettingKeyEnum::BIG_BLUE_BUTTON, array_merge($this->config, [
        'enabled' => false,
    ]), 'json', 'integrations');

    $service = $this->mock(BbbService::class);
    $service->shouldReceive('isEnabled')->andReturn(false);
    $service->shouldNotReceive('createMeeting');

    $enrollment = createBbbEnrollmentForJob();

    $job = new ProvisionBbbEnrollmentJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();
    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PENDING_PROVISIONING)
        ->and($enrollment->provisioning_data)->not->toHaveKey('providers.bbb');
});

it('throws when bbb configuration is missing base_url or secret', function (): void {
    Setting::setValue(SettingKeyEnum::BIG_BLUE_BUTTON, array_merge($this->config, [
        'base_url' => '',
    ]), 'json', 'integrations');

    $job = new ProvisionBbbEnrollmentJob(1);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'BbbService configuration is missing or invalid.');

    Setting::setValue(SettingKeyEnum::BIG_BLUE_BUTTON, array_merge($this->config, [
        'secret' => '',
    ]), 'json', 'integrations');

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'BbbService configuration is missing or invalid.');
});

it('returns when enrollment does not exist', function (): void {
    $service = $this->mock(BbbService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldNotReceive('createMeeting');

    $job = new ProvisionBbbEnrollmentJob(999999);
    $job->handle();

    // No exception thrown; no enrollment to mutate — assert job completes cleanly
    expect(Enrollment::find(999999))->toBeNull();
});

it('throws when bbb meeting id is missing', function (): void {
    $enrollment = createBbbEnrollmentForJob([
        'meeting_id' => null,
    ]);

    $service = $this->mock(BbbService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');

    $job = new ProvisionBbbEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'BBB meeting_id is missing from delivery option details.');
});

it('provisions bbb enrollment without creating meeting when auto create disabled', function (): void {
    $enrollment = createBbbEnrollmentForJob([
        'meeting_id'          => 'BBB-MEET-1',
        'auto_create_meeting' => false,
        'attendee_password'   => 'ap-1',
    ]);

    $service = $this->mock(BbbService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldNotReceive('createMeeting');

    $job = new ProvisionBbbEnrollmentJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.bbb.status'))->toBe('success')
        ->and(data_get($enrollment->provisioning_data, 'providers.bbb.data.meeting_id'))->toBe('BBB-MEET-1')
        ->and(data_get($enrollment->provisioning_data, 'providers.bbb.data.join_url'))->toBeNull()
        ->and($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE);
});

it('creates meeting when auto create enabled', function (): void {
    $enrollment = createBbbEnrollmentForJob([
        'meeting_id'          => 'BBB-MEET-2',
        'auto_create_meeting' => true,
        'attendee_password'   => 'ap-2',
        'moderator_password'  => 'mp-2',
    ]);

    $service = $this->mock(BbbService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('createMeeting')
        ->once()
        ->with('BBB-MEET-2', $enrollment->productDeliveryOption->name, 'ap-2', 'mp-2');

    $job = new ProvisionBbbEnrollmentJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.bbb.data.meeting_id'))->toBe('BBB-MEET-2')
        ->and(data_get($enrollment->provisioning_data, 'providers.bbb.data.join_url'))->toBeNull()
        ->and($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE);
});

it('marks provisioning failure on failed callback', function (): void {
    $enrollment = createBbbEnrollmentForJob([
        'meeting_id' => 'BBB-MEET-3',
    ]);

    $job = new ProvisionBbbEnrollmentJob($enrollment->id);
    $job->failed(new RuntimeException('bbb failed hard'));

    $enrollment->refresh();

    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PROVISIONING_FAILED)
        ->and(data_get($enrollment->provisioning_data, 'providers.bbb.status'))->toBe('failed')
        ->and(data_get($enrollment->provisioning_data, 'providers.bbb.last_error'))->toBe('bbb failed hard');
});

it('returns from failed callback when enrollment does not exist', function (): void {
    $job = new ProvisionBbbEnrollmentJob(999999);

    $job->failed(new RuntimeException('bbb failed hard'));

    // No exception thrown; no enrollment to mutate — assert job completes cleanly
    expect(Enrollment::find(999999))->toBeNull();
});

it('returns configured backoff values', function (): void {
    $job = new ProvisionBbbEnrollmentJob(1);

    expect($job->backoff())->toBe([60, 180, 600]);
});

function createBbbEnrollmentForJob(array $detailsOverrides = []): Enrollment
{
    $customer = User::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    $order = Order::factory()->for($customer, 'customer')->create();

    $details = array_merge([
        'meeting_id'          => 'BBB-DEFAULT',
        'auto_create_meeting' => false,
    ], $detailsOverrides);

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::LIVE_SESSION_BBB,
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
