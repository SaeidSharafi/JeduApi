<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Jobs\Provisioning\ProvisionSpotPlayerEnrollmentJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Integrations\SpotPlayerService;

it('returns when enrollment does not exist', function (): void {
    $service = $this->mock(SpotPlayerService::class);
    $service->shouldNotReceive('issueLicense');

    $job = new ProvisionSpotPlayerEnrollmentJob(999999);
    $job->handle($service);

    expect(true)->toBeTrue();
});

it('throws when spotplayer course id is missing', function (): void {
    $enrollment = createSpotPlayerEnrollmentForJob([
        'course_id' => null,
    ]);

    $service = $this->mock(SpotPlayerService::class);

    $job = new ProvisionSpotPlayerEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle($service))
        ->toThrow(RuntimeException::class, 'SpotPlayer spot_id is missing from delivery option details.');
});

it('provisions spotplayer enrollment and saves provisioning data', function (): void {
    $enrollment = createSpotPlayerEnrollmentForJob([
        'course_id' => 'SPOT-COURSE-99',
    ]);

    $service = $this->mock(SpotPlayerService::class);
    $service->shouldReceive('issueLicense')
        ->once()
        ->with('SPOT-COURSE-99', Mockery::on(fn ($user): bool => $user instanceof User && $user->is($enrollment->customer)))
        ->andReturn([
            'license_key' => 'LIC-99',
            'player_url'  => 'https://player.example/99',
            'raw'         => ['status' => true],
        ]);

    $job = new ProvisionSpotPlayerEnrollmentJob($enrollment->id);
    $job->handle($service);

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.spotplayer.status'))->toBe('success')
        ->and(data_get($enrollment->provisioning_data, 'providers.spotplayer.data.spot_id'))->toBe('SPOT-COURSE-99')
        ->and(data_get($enrollment->provisioning_data, 'providers.spotplayer.data.license_key'))->toBe('LIC-99')
        ->and(data_get($enrollment->provisioning_data, 'providers.spotplayer.data.player_url'))->toBe('https://player.example/99')
        ->and($enrollment->enrollment_status)->not->toBe(EnrollmentStatusEnum::ACTIVE);
});

it('marks provisioning failure on failed callback', function (): void {
    $enrollment = createSpotPlayerEnrollmentForJob();

    $job = new ProvisionSpotPlayerEnrollmentJob($enrollment->id);
    $job->failed(new RuntimeException('spotplayer failed hard'));

    $enrollment->refresh();

    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PROVISIONING_FAILED)
        ->and(data_get($enrollment->provisioning_data, 'providers.spotplayer.status'))->toBe('failed')
        ->and(data_get($enrollment->provisioning_data, 'providers.spotplayer.last_error'))->toBe('spotplayer failed hard');
});

it('returns from failed callback when enrollment does not exist', function (): void {
    $job = new ProvisionSpotPlayerEnrollmentJob(999999);

    $job->failed(new RuntimeException('spotplayer failed hard'));

    expect(true)->toBeTrue();
});

it('returns configured backoff values', function (): void {
    $job = new ProvisionSpotPlayerEnrollmentJob(1);

    expect($job->backoff())->toBe([60, 180, 600]);
});

function createSpotPlayerEnrollmentForJob(array $detailsOverrides = []): Enrollment
{
    $customer = User::factory()->create();
    $order    = Order::factory()->for($customer, 'customer')->create();

    $details = array_merge([
        'course_id'       => 'SPOT-DEFAULT',
        'ims_course_code' => 'IMS-SPOT-1',
    ], $detailsOverrides);

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
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
