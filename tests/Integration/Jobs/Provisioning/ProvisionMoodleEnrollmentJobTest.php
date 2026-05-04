<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Jobs\Provisioning\ProvisionMoodleEnrollmentJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Integrations\MoodleService;

it('returns when enrollment does not exist', function (): void {
    $service = $this->mock(MoodleService::class);
    $service->shouldNotReceive('findOrCreateUser');

    $job = new ProvisionMoodleEnrollmentJob(999999);
    $job->handle($service);

    expect(true)->toBeTrue();
});

it('throws when moodle course id is missing', function (): void {
    $enrollment = createMoodleEnrollmentForJob([
        'moodle_course_id' => 'not-int',
    ]);

    $service = $this->mock(MoodleService::class);

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle($service))
        ->toThrow(RuntimeException::class, 'Moodle course id is missing from delivery option details.');
});

it('provisions moodle enrollment with parsed start and end dates', function (): void {
    config([
        'services.moodle.default_login_redirect_script' => '/custom-login/',
    ]);

    $enrollment = createMoodleEnrollmentForJob([
        'moodle_course_id'      => 123,
        'enrollment_start_date' => '2026-01-02 10:11:12',
        'enrollment_end_date'   => '2026-02-03 11:12:13',
    ]);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('findOrCreateUser')
        ->once()
        ->with(

            Mockery::on(fn ($user): bool => $user instanceof User && $user->is($enrollment->customer))
        )
        ->andReturn(987);
    $service->shouldReceive('enrollUser')
        ->once()
        ->with(987, 123, strtotime('2026-01-02 10:11:12'), strtotime('2026-02-03 11:12:13'));
    $service->shouldReceive('createUserKey')
        ->once()
        ->with(987)
        ->andReturn('user-key-xyz');

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->handle($service);

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.moodle.status'))->toBe('success')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_id'))->toBe(987)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_course_id'))->toBe(123)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.auth_userkey'))->toBe('user-key-xyz')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.login_path'))->toBe('/custom-login/')
        ->and($enrollment->enrollment_status)->not->toBe(EnrollmentStatusEnum::ACTIVE);
});

it('passes null timestamps when enrollment dates are invalid', function (): void {
    $enrollment = createMoodleEnrollmentForJob([
        'moodle_course_id'      => 124,
        'enrollment_start_date' => 'invalid-date',
        'enrollment_end_date'   => null,
    ]);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('findOrCreateUser')->once()->andReturn(988);
    $service->shouldReceive('enrollUser')
        ->once()
        ->with(988, 124, null, null);
    $service->shouldReceive('createUserKey')->once()->andReturn('user-key-null-time');

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->handle($service);

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.moodle.status'))->toBe('success');
});

it('marks provisioning failure on failed callback', function (): void {
    $enrollment = createMoodleEnrollmentForJob();

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->failed(new RuntimeException('moodle failed hard'));

    $enrollment->refresh();

    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PROVISIONING_FAILED)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.status'))->toBe('failed')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.last_error'))->toBe('moodle failed hard');
});

it('returns from failed callback when enrollment does not exist', function (): void {
    $job = new ProvisionMoodleEnrollmentJob(999999);

    $job->failed(new RuntimeException('moodle failed hard'));

    expect(true)->toBeTrue();
});

it('returns configured backoff values', function (): void {
    $job = new ProvisionMoodleEnrollmentJob(1);

    expect($job->backoff())->toBe([60, 180, 600]);
});

function createMoodleEnrollmentForJob(array $detailsOverrides = []): Enrollment
{
    $customer = User::factory()->create();
    $order    = Order::factory()->for($customer, 'customer')->create();

    $details = array_merge([
        'moodle_course_id'      => 100,
        'enrollment_start_date' => '2026-01-01',
        'enrollment_end_date'   => '2026-02-01',
        'ims_course_code'       => 'IMS-MOODLE-1',
    ], $detailsOverrides);

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
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
