<?php

declare(strict_types=1);

use App\Data\Shop\MyCourses\Blocks\LmsMoodleBlockData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Jobs\Provisioning\ProvisionMoodleEnrollmentJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\MoodleService;

beforeEach(function () {
    $this->config = [
        'enabled'                       => true,
        'base_url'                      => 'https://moodle.test',
        'token'                         => 'moodle-token-abc',
        'auth_userkey_token'            => 'moodle-auth-userkey-token-xyz',
        'default_role_id'               => 5,
        'default_login_redirect_script' => '/my/',
    ];
    Setting::setValue(SettingKeyEnum::MOODLE, $this->config, 'json', 'integrations');
});

it('returns when moodle integration is disabled', function (): void {
    Setting::setValue(SettingKeyEnum::MOODLE, array_merge($this->config, ['enabled' => false]), 'json', 'integrations');

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('setConfig')->never();
    $service->shouldNotReceive('findOrCreateUser');
    $service->shouldNotReceive('enrollUser');
    $service->shouldNotReceive('createUserKey');

    $enrollment = createMoodleEnrollmentForJob();

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->handle($service);

    expect(true)->toBeTrue();
});

it('throws when moodle configuration is missing base_url or token', function (): void {
    Setting::setValue(SettingKeyEnum::MOODLE, array_merge($this->config, ['base_url' => '']), 'json', 'integrations');

    $service = $this->mock(MoodleService::class);
    $job     = new ProvisionMoodleEnrollmentJob(1);

    expect(fn () => $job->handle($service))
        ->toThrow(RuntimeException::class, 'Moodle configuration is missing base_url or token.');
});
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
    $enrollment = createMoodleEnrollmentForJob([
        'moodle_course_id'      => 123,
        'enrollment_start_date' => '2026-01-02 10:11:12',
        'enrollment_end_date'   => '2026-02-03 11:12:13',
    ]);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('setConfig')
        ->once()
        ->with($this->config);
    $service->shouldReceive('findOrCreateUser')
        ->once()
        ->with(

            Mockery::on(fn ($user): bool => $user instanceof User && $user->is($enrollment->customer))
        )
        ->andReturn([987, 'user1']);
    $service->shouldReceive('enrollUser')
        ->once()
        ->with(987, 123, strtotime('2026-01-02 10:11:12'), strtotime('2026-02-03 11:12:13'), 5);
    $service->shouldReceive('getCourse')->once()->with(123)->andReturn(new LmsMoodleBlockData(visible: true, name: 'Course 123', course_url: null, completed: false));
    $service->shouldNotReceive('createUserKey');

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->handle($service);

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.moodle.status'))->toBe('success')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_id'))->toBe(987)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_course_id'))->toBe(123)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.login_path'))->toBe('/my/');
});

it('passes null timestamps when enrollment dates are invalid', function (): void {
    $enrollment = createMoodleEnrollmentForJob([
        'moodle_course_id'      => 124,
        'enrollment_start_date' => 'invalid-date',
        'enrollment_end_date'   => null,
    ]);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('setConfig')
        ->with($this->config);
    $service->shouldReceive('findOrCreateUser')->once()->andReturn([988, 'user1']);
    $service->shouldReceive('enrollUser')
        ->once()
        ->with(988, 124, null, null, 5);
    $service->shouldReceive('getCourse')->once()->with(124)->andReturn(new LmsMoodleBlockData(visible: true, name: 'Course 124', course_url: null, completed: false));
    $service->shouldNotReceive('createUserKey');

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

it('stores course_info in provisioning_data after successful provisioning', function (): void {
    $enrollment = createMoodleEnrollmentForJob([
        'moodle_course_id' => 200,
    ]);

    $courseInfo = new LmsMoodleBlockData(visible: true, name: 'Test Course', course_url: null, completed: false);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('setConfig')->once()->with($this->config);
    $service->shouldReceive('findOrCreateUser')->once()->andReturn([500, 'user500']);
    $service->shouldReceive('enrollUser')->once();
    $service->shouldReceive('getCourse')->once()->with(200)->andReturn($courseInfo);

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->handle($service);

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.moodle.data.course_info'))
        ->toMatchArray([
            'visible'    => true,
            'name'       => 'Test Course',
            'course_url' => null,
            'completed'  => false,
        ]);
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
