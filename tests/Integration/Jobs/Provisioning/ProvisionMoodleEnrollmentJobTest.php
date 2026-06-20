<?php

declare(strict_types=1);

use App\Data\Shop\MyCourses\Blocks\LmsMoodleBlockData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
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
    $service->shouldReceive('isEnabled')->andReturn(false);
    $service->shouldNotReceive('findOrCreateUser');
    $service->shouldNotReceive('enrollUser');
    $service->shouldNotReceive('createUserKey');

    $enrollment = createMoodleEnrollmentForJob();

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();
    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PENDING_PROVISIONING)
        ->and($enrollment->provisioning_data)->not->toHaveKey('providers.moodle');
});

it('throws when moodle configuration is missing base_url or token', function (): void {
    Setting::setValue(SettingKeyEnum::MOODLE, array_merge($this->config, ['base_url' => '']), 'json', 'integrations');

    $job = new ProvisionMoodleEnrollmentJob(1);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'MoodleService configuration is missing or invalid.');
});

it('returns when enrollment does not exist', function (): void {
    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldNotReceive('findOrCreateUser');

    $job = new ProvisionMoodleEnrollmentJob(999999);
    $job->handle();

    // No exception thrown; no enrollment to mutate — assert job completes cleanly
    expect(Enrollment::find(999999))->toBeNull();
});

it('throws when moodle course id is missing', function (): void {
    $enrollment = createMoodleEnrollmentForJob([
        'moodle_course_id' => 'not-int',
    ]);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle course_id is missing from delivery option details.');
});

it('provisions moodle enrollment with parsed start and end dates', function (): void {
    $enrollment = createMoodleEnrollmentForJob([
        'moodle_course_id'      => 123,
        'enrollment_start_date' => '2026-01-02 10:11:12',
        'enrollment_end_date'   => '2026-02-03 11:12:13',
    ]);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('getDefaultRoleId')->andReturn(5);
    $service->shouldReceive('getLoginPath')->andReturn('/my/');
    $service->shouldReceive('findOrCreateUser')
        ->once()
        ->with(
            Mockery::on(fn ($user): bool => $user instanceof User && $user->is($enrollment->customer))
        )
        ->andReturn([987, 'user1']);
    $service->shouldReceive('getCourse')
        ->once()
        ->with(123)
        ->andReturn(LmsMoodleBlockData::from([
            'visible'      => true,
            'name'         => 'Test Course',
            'course_url'   => 'https://moodle.test/course/view.php?id=123',
            'completed'    => false,
            'course_grade' => null,
            'activities'   => [],
        ]));
    $service->shouldReceive('enrollUser')
        ->once()
        ->with(987, 123, strtotime('2026-01-02 10:11:12'), strtotime('2026-02-03 11:12:13'), 5);
    $service->shouldNotReceive('createUserKey');

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.moodle.status'))->toBe('success')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_id'))->toBe(987)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_name'))->toBe('user1')
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
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('getDefaultRoleId')->andReturn(5);
    $service->shouldReceive('getLoginPath')->andReturn('/my/');
    $service->shouldReceive('findOrCreateUser')->once()->andReturn([988, 'user1']);
    $service->shouldReceive('getCourse')->once()->with(124)->andReturn(LmsMoodleBlockData::from([
        'visible'      => true,
        'name'         => 'Test Course',
        'course_url'   => 'https://moodle.test/course/view.php?id=124',
        'completed'    => false,
        'course_grade' => null,
        'activities'   => [],
    ]));
    $service->shouldReceive('enrollUser')
        ->once()
        ->with(988, 124, null, null, 5);
    $service->shouldNotReceive('createUserKey');

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->handle();

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

    // No exception thrown; no enrollment to mutate — assert job completes cleanly
    expect(Enrollment::find(999999))->toBeNull();
});

it('returns configured backoff values', function (): void {
    $job = new ProvisionMoodleEnrollmentJob(1);

    expect($job->backoff())->toBe([60, 180, 600]);
});

it('stores computed course_url and renamed username in provisioning_data after successful provisioning', function (): void {
    $enrollment = createMoodleEnrollmentForJob([
        'moodle_course_id' => 200,
    ]);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('getDefaultRoleId')->andReturn(5);
    $service->shouldReceive('getLoginPath')->andReturn('/my/');
    $service->shouldReceive('findOrCreateUser')->once()->andReturn([500, 'user500']);
    $service->shouldReceive('getCourse')->once()->with(200)->andReturn(LmsMoodleBlockData::from([
        'visible'      => true,
        'name'         => 'Test Course',
        'course_url'   => 'https://moodle.test/course/view.php?id=200',
        'completed'    => false,
        'course_grade' => null,
        'activities'   => [],
    ]));
    $service->shouldReceive('enrollUser')->once();

    $job = new ProvisionMoodleEnrollmentJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_name'))->toBe('user500')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.course_info'))->toBeArray();
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
