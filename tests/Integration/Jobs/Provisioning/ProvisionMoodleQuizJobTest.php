<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\System\SettingKeyEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Jobs\Provisioning\ProvisionMoodleQuizJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\Setting;
use App\Models\User;
use App\Services\Integrations\MoodleService;

beforeEach(function () {
    $this->config = [
        'enabled'  => true,
        'base_url' => 'https://moodle.test',
        'token'    => 'moodle-token-abc',
    ];
    Setting::setValue(SettingKeyEnum::MOODLE, $this->config, 'json', 'integrations');
});

it('returns when moodle integration is disabled', function (): void {
    Setting::setValue(SettingKeyEnum::MOODLE, array_merge($this->config, ['enabled' => false]), 'json', 'integrations');

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturn(false);
    $service->shouldNotReceive('findOrCreateUser');
    $service->shouldNotReceive('enrollUser');

    $enrollment = createMoodleQuizEnrollmentForJob();

    $job = new ProvisionMoodleQuizJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();
    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PENDING_PROVISIONING)
        ->and($enrollment->provisioning_data)->not->toHaveKey('providers.moodle_quiz');
});

it('throws when moodle configuration is missing base_url or token', function (): void {
    Setting::setValue(SettingKeyEnum::MOODLE, array_merge($this->config, ['base_url' => '']), 'json', 'integrations');

    $job = new ProvisionMoodleQuizJob(1);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'MoodleService configuration is missing or invalid.');
});

it('returns when enrollment does not exist', function (): void {
    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldNotReceive('findOrCreateUser');

    $job = new ProvisionMoodleQuizJob(999999);
    $job->handle();

    expect(Enrollment::find(999999))->toBeNull();
});

it('throws when moodle quiz course id is not numeric', function (): void {
    $enrollment = createMoodleQuizEnrollmentForJob([
        'moodle_quiz_course_id' => 'not-int',
    ]);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');

    $job = new ProvisionMoodleQuizJob($enrollment->id);

    expect(fn () => $job->handle())
        ->toThrow(UnrecoverableProvisioningException::class, 'Moodle quiz course_id is missing from delivery option details.');
});

it('provisions moodle quiz enrollment with no date window', function (): void {
    $enrollment = createMoodleQuizEnrollmentForJob([
        'moodle_quiz_course_id' => 456,
    ]);

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('isEnabled')->andReturn(true);
    $service->shouldReceive('assertConfigured');
    $service->shouldReceive('getDefaultRoleId')->andReturn(5);
    $service->shouldReceive('findOrCreateUser')
        ->once()
        ->with(
            Mockery::on(fn ($user): bool => $user instanceof User && $user->is($enrollment->customer))
        )
        ->andReturn([789, 'quizuser']);
    $service->shouldReceive('enrollUser')
        ->once()
        ->with(789, 456, null, null, 5);

    $job = new ProvisionMoodleQuizJob($enrollment->id);
    $job->handle();

    $enrollment->refresh();

    expect(data_get($enrollment->provisioning_data, 'providers.moodle_quiz.status'))->toBe('success')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data.moodle_user_id'))->toBe(789)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data.moodle_username'))->toBe('quizuser')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data.moodle_course_id'))->toBe(456);
});

it('marks provisioning failure on failed callback', function (): void {
    $enrollment = createMoodleQuizEnrollmentForJob();

    $job = new ProvisionMoodleQuizJob($enrollment->id);
    $job->failed(new RuntimeException('moodle quiz failed'));

    $enrollment->refresh();

    expect($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::PROVISIONING_FAILED)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle_quiz.status'))->toBe('failed')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle_quiz.last_error'))->toBe('moodle quiz failed');
});

it('returns from failed callback when enrollment does not exist', function (): void {
    $job = new ProvisionMoodleQuizJob(999999);

    $job->failed(new RuntimeException('moodle quiz failed'));

    expect(Enrollment::find(999999))->toBeNull();
});

it('returns configured backoff values', function (): void {
    $job = new ProvisionMoodleQuizJob(1);

    expect($job->backoff())->toBe([60, 180, 600]);
});

// ─── Helper ───────────────────────────────────────────────────────────────────

function createMoodleQuizEnrollmentForJob(array $detailsOverrides = []): Enrollment
{
    $customer = User::factory()->create();
    $order    = Order::factory()->for($customer, 'customer')->create();

    $details = array_merge([
        'moodle_quiz_course_id' => 200,
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
