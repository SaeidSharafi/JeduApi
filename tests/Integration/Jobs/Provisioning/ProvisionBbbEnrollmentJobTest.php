<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Jobs\Provisioning\ProvisionBbbEnrollmentJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Integrations\BbbService;

beforeEach(function (): void {
    config([
        'services.bbb.default_attendee_password'  => 'ap-default',
        'services.bbb.default_moderator_password' => 'mp-default',
    ]);
});

it('returns when enrollment does not exist', function (): void {
    $service = $this->mock(BbbService::class);
    $service->shouldNotReceive('createMeeting');
    $service->shouldNotReceive('buildJoinUrl');

    $job = new ProvisionBbbEnrollmentJob(999999);
    $job->handle($service);

    expect(true)->toBeTrue();
});

it('throws when bbb meeting id is missing', function (): void {
    $enrollment = createBbbEnrollmentForJob([
        'meeting_id' => null,
    ]);

    $service = $this->mock(BbbService::class);

    $job = new ProvisionBbbEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle($service))
        ->toThrow(RuntimeException::class, 'BBB meeting_id is missing from delivery option details.');
});

it('provisions bbb enrollment without creating meeting when auto create disabled', function (): void {
    $enrollment = createBbbEnrollmentForJob([
        'meeting_id'          => 'BBB-MEET-1',
        'auto_create_meeting' => false,
        'attendee_password'   => 'ap-1',
    ]);

    $service = $this->mock(BbbService::class);
    $service->shouldNotReceive('createMeeting');
    $service->shouldReceive('buildJoinUrl')
        ->once()
        ->with('BBB-MEET-1', 'John Doe', 'ap-1')
        ->andReturn('https://bbb.test/join/BBB-MEET-1');

    $job = new ProvisionBbbEnrollmentJob($enrollment->id);
    $job->handle($service);

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.bbb.status'))->toBe('success')
        ->and(data_get($enrollment->provisioning_data, 'providers.bbb.data.meeting_id'))->toBe('BBB-MEET-1')
        ->and(data_get($enrollment->provisioning_data, 'providers.bbb.data.attendee_join_url'))->toBe('https://bbb.test/join/BBB-MEET-1')
        ->and($enrollment->enrollment_status)->not->toBe(EnrollmentStatusEnum::ACTIVE);
});

it('creates meeting when auto create enabled', function (): void {
    $enrollment = createBbbEnrollmentForJob([
        'meeting_id'          => 'BBB-MEET-2',
        'auto_create_meeting' => true,
        'attendee_password'   => 'ap-2',
        'moderator_password'  => 'mp-2',
    ]);

    $service = $this->mock(BbbService::class);
    $service->shouldReceive('createMeeting')
        ->once()
        ->with('BBB-MEET-2', $enrollment->productDeliveryOption->name, 'ap-2', 'mp-2');
    $service->shouldReceive('buildJoinUrl')
        ->once()
        ->with('BBB-MEET-2', 'John Doe', 'ap-2')
        ->andReturn('https://bbb.test/join/BBB-MEET-2');

    $job = new ProvisionBbbEnrollmentJob($enrollment->id);
    $job->handle($service);

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.bbb.data.auto_create_meeting'))->toBeTrue();
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

    expect(true)->toBeTrue();
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
        'ims_course_code'     => 'IMS-BBB-1',
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
