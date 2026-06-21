<?php

declare(strict_types=1);

use App\Data\Shop\MyCourses\Blocks\LmsMoodleBlockData;
use App\Data\Shop\MyCourses\Blocks\MoodleActivityData;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Jobs\Provisioning\SyncMoodleProgressJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Integrations\MoodleService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

it('returns when enrollment does not exist', function (): void {
    $service = $this->mock(MoodleService::class);
    $service->shouldNotReceive('isReady');
    $service->shouldNotReceive('getCourse');

    $job = new SyncMoodleProgressJob(999999, 100, 200);
    $job->handle(app(MoodleService::class));

    expect(Enrollment::find(999999))->toBeNull();
});

it('returns when moodle is not ready', function (): void {
    $enrollment = createEnrollmentForSync();

    $service = $this->mock(MoodleService::class);
    $service->shouldReceive('isReady')->once()->andReturn(false);
    $service->shouldNotReceive('getCourse');

    $job = new SyncMoodleProgressJob($enrollment->id, 100, 200);
    $job->handle(app(MoodleService::class));

    // Enrollment should not be mutated
    $enrollment->refresh();
    expect(data_get($enrollment->provisioning_data, 'providers.moodle.sync'))->toBeNull();
});

it('syncs course progress into enrollment provisioning_data', function (): void {
    $enrollment     = createEnrollmentForSync();
    $moodleCourseId = 100;
    $moodleUserId   = 200;

    $activities = [
        new MoodleActivityData(
            url: 'https://moodle.test/mod/page/view.php?id=1',
            cid: 10,
            name: 'Activity One',
            type: 'page',
            state: 2,
            grade: null,
            timecompleted: null,
        ),
    ];

    $moodleService = $this->mock(MoodleService::class);
    $moodleService->shouldReceive('isReady')->once()->andReturn(true);
    $moodleService->shouldReceive('getCourse')
        ->once()
        ->with($moodleCourseId)
        ->andReturn(LmsMoodleBlockData::from([
            'visible'      => true,
            'name'         => 'Test Course',
            'course_url'   => 'https://moodle.test/course/view.php?id=100',
            'completed'    => false,
            'course_grade' => null,
            'activities'   => $activities,
        ]));
    $moodleService->shouldReceive('isCourseCompleted')
        ->once()
        ->with($moodleCourseId, $moodleUserId)
        ->andReturn(false);
    $moodleService->shouldReceive('getActivityCompletionStatus')
        ->once()
        ->with($moodleCourseId, $moodleUserId)
        ->andReturn(['10' => ['state' => 2, 'timecompleted' => null]]);
    $moodleService->shouldReceive('getGrades')
        ->once()
        ->with($moodleCourseId, $moodleUserId)
        ->andReturn(['activities' => ['10' => '85.5'], 'course_grade' => 'B']);

    $job = new SyncMoodleProgressJob($enrollment->id, $moodleCourseId, $moodleUserId);
    $job->handle(app(MoodleService::class));

    $enrollment->refresh();
    $sync = data_get($enrollment->provisioning_data, 'providers.moodle.sync');

    expect($sync)->not->toBeNull()
        ->and($sync['completed'])->toBeFalse()
        ->and($sync['course_grade'])->toBeNull() // survey_completed_at is null
        ->and($sync['activities'])->toHaveCount(1)
        ->and($sync['activities'][0]['cmid'])->toBe(10)
        ->and($sync['activities'][0]['name'])->toBe('Activity One')
        ->and($sync['activities'][0]['state'])->toBe(2)
        ->and($sync['activities'][0]['score'])->toBeNull() // survey_completed_at is null
        ->and($sync['synced_at'])->not->toBeNull();
});

it('includes score and course_grade when survey is completed', function (): void {
    $enrollment     = createEnrollmentForSync(['survey_completed_at' => now()]);
    $moodleCourseId = 100;
    $moodleUserId   = 200;

    $activities = [
        new MoodleActivityData(
            url: 'https://moodle.test/mod/quiz/view.php?id=5',
            cid: 20,
            name: 'Quiz One',
            type: 'quiz',
            state: 1,
            grade: null,
            timecompleted: '1700000000',
        ),
    ];

    $moodleService = $this->mock(MoodleService::class);
    $moodleService->shouldReceive('isReady')->once()->andReturn(true);
    $moodleService->shouldReceive('getCourse')
        ->once()
        ->with($moodleCourseId)
        ->andReturn(LmsMoodleBlockData::from([
            'visible'      => true,
            'name'         => 'Test Course',
            'course_url'   => 'https://moodle.test/course/view.php?id=100',
            'completed'    => true,
            'course_grade' => null,
            'activities'   => $activities,
        ]));
    $moodleService->shouldReceive('isCourseCompleted')
        ->once()
        ->with($moodleCourseId, $moodleUserId)
        ->andReturn(true);
    $moodleService->shouldReceive('getActivityCompletionStatus')
        ->once()
        ->with($moodleCourseId, $moodleUserId)
        ->andReturn(['20' => ['state' => 1, 'timecompleted' => '1700000000']]);
    $moodleService->shouldReceive('getGrades')
        ->once()
        ->with($moodleCourseId, $moodleUserId)
        ->andReturn(['activities' => ['20' => '95.0'], 'course_grade' => 'A+']);

    $job = new SyncMoodleProgressJob($enrollment->id, $moodleCourseId, $moodleUserId);
    $job->handle(app(MoodleService::class));

    $enrollment->refresh();
    $sync = data_get($enrollment->provisioning_data, 'providers.moodle.sync');

    expect($sync['completed'])->toBeTrue()
        ->and($sync['course_grade'])->toBe('A+')
        ->and($sync['activities'][0]['score'])->toBe('95.0')
        ->and($sync['activities'][0]['timecompleted'])->toBe('1700000000');
});

it('returns configured backoff values', function (): void {
    $job = new SyncMoodleProgressJob(1, 100, 200);

    expect($job->backoff())->toBe([60, 180]);
});

it('logs error and clears rate limiter on failure', function (): void {
    Log::shouldReceive('error')->once()->with(
        'Failed to sync Moodle progress after 3 attempts.',
        Mockery::on(function (array $context): bool {
            return $context['enrollment_id']    === 42
                && $context['moodle_course_id'] === 100
                && $context['moodle_user_id']   === 200;
        })
    );

    RateLimiter::shouldReceive('clear')->once()->with(
        'throttle:moodle-sync:42:100:200'
    );

    $job = new SyncMoodleProgressJob(42, 100, 200);
    $job->failed(new RuntimeException('sync failed'));
});

// ─── Helper ───────────────────────────────────────────────────────────────────

function createEnrollmentForSync(array $enrollmentOverrides = []): Enrollment
{
    $customer = User::factory()->create();
    $order    = Order::factory()->for($customer, 'customer')->create();

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
    ]);

    $item = OrderItem::factory()
        ->for($order)
        ->for($deliveryOption, 'productDeliveryOption')
        ->create();

    return Enrollment::factory()->for($item)->create(array_merge([
        'enrollment_status'   => EnrollmentStatusEnum::ACTIVE,
        'provisioning_data'   => ['providers' => ['moodle' => ['status' => 'success']]],
        'survey_completed_at' => null,
    ], $enrollmentOverrides));
}
