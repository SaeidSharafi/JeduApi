<?php

declare(strict_types=1);

use App\Actions\Shop\Student\GetJoinUrlAction;
use App\Contracts\Integrations\BbbClientContract;
use App\Contracts\Integrations\ImsClientContract;
use App\Contracts\Integrations\MoodleClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Contracts\Integrations\SpotPlayerClientContract;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Enrollment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductDeliveryOption;
use App\Models\Staff;
use App\Services\Fakes\FakeBbbService;
use App\Services\Fakes\FakeImsService;
use App\Services\Fakes\FakeMoodleService;
use App\Services\Fakes\FakeSkyroomService;
use App\Services\Fakes\FakeSpotPlayerService;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleQuizProvisioningProvider;
use App\Services\Provisioning\Providers\SpotPlayerProvisioningProvider;
use App\Services\Provisioning\ProvisioningAttemptService;
use App\Services\Provisioning\ProvisioningProviderRegistry;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Queue;

function reconciliationEnrollment(string $provider, array $data = []): Enrollment
{
    $enrollment = Enrollment::factory()->create([
        'enrollment_status'   => EnrollmentStatusEnum::ACTIVE,
        'provisioning_status' => ProvisioningStatusEnum::HEALTHY,
    ]);
    $enrollment->update([
        'provisioning_plan' => [
            'version'   => 1,
            'providers' => [['provider' => $provider, 'applicable' => true, 'readiness' => 'ready']],
        ],
        'provisioning_data' => ['providers' => [$provider => ['status' => 'success', 'data' => $data]]],
    ]);

    return $enrollment->fresh();
}

it('runs Moodle through the provider boundary and activates the enrollment', function (): void {
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
    ]);
    $enrollment->update([
        'provisioning_plan' => [
            'version'   => 1,
            'providers' => [
                [
                    'provider'            => 'moodle',
                    'applicable'          => true,
                    'readiness'           => 'ready',
                    'configuration_issue' => null,
                ],
            ],
            'status'      => ProvisioningStatusEnum::READY->value,
            'resolved_at' => now()->toISOString(),
        ],
    ]);

    $provider = $this->mock(MoodleProvisioningProvider::class);
    $provider->shouldReceive('provision')->once()->withArgs(fn (Enrollment $value): bool => $value->is($enrollment))
        ->andReturn([
            'moodle_user_id'   => 42,
            'moodle_course_id' => 99,
            'login_path'       => '/my/',
        ]);

    $attempt = app(ProvisioningAttemptService::class)->queue($enrollment, ProvisioningTriggerEnum::PAYMENT);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle(
        app(ProvisioningAttemptService::class),
        app(ProvisioningProviderRegistry::class),
    );

    $enrollment->refresh();
    $attempt->refresh();

    expect($attempt->status->value)->toBe('succeeded')
        ->and($enrollment->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE)
        ->and($enrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data.moodle_user_id'))->toBe(42)
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle.data'))->not->toHaveKey('raw_payload');
});

it('provisions Moodle with the simulated client through the queued lifecycle', function (): void {
    app()->instance(
        MoodleClientContract::class,
        new FakeMoodleService(app(SettingsService::class)),
    );
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::LMS_MOODLE,
        'details_json'    => ['moodle_course_id' => 321],
    ]);
    $orderItem = OrderItem::factory()->create([
        'product_delivery_option_id' => $option->id,
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_item_id'              => $orderItem->id,
        'order_id'                   => $orderItem->order_id,
        'customer_id'                => $orderItem->order->customer_id,
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        'provisioning_plan'          => [
            'version'   => 1,
            'providers' => [['provider' => 'moodle', 'applicable' => true, 'readiness' => 'ready']],
            'status'    => ProvisioningStatusEnum::READY->value,
        ],
    ]);
    $attempts = app(ProvisioningAttemptService::class);
    $attempt  = $attempts->queue($enrollment, ProvisioningTriggerEnum::PAYMENT);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    $enrollment->refresh();
    $firstData = data_get($enrollment->provisioning_data, 'providers.moodle.data');

    expect($attempt->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and($enrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($firstData['moodle_user_id'])->toBe(1000 + $enrollment->customer_id)
        ->and($firstData['moodle_course_id'])->toBe(321)
        ->and($firstData)->not->toHaveKey('raw_payload');

    $retry = $attempts->queue($enrollment, ProvisioningTriggerEnum::RETRY);
    (new ProvisionEnrollmentProviderJob($retry->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    expect($retry->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and(data_get($enrollment->fresh()->provisioning_data, 'providers.moodle.data.moodle_user_id'))
        ->toBe($firstData['moodle_user_id']);
});

it('provisions IMS with stable simulated references through the queued lifecycle', function (): void {
    app()->instance(ImsClientContract::class, new FakeImsService());
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::IN_PERSON,
        'details_json'    => ['ims_course_code' => 'IMS-E2E-76'],
    ]);
    $orderItem = OrderItem::factory()->create([
        'product_delivery_option_id' => $option->id,
    ]);
    Payment::factory()->create([
        'order_id'    => $orderItem->order_id,
        'customer_id' => $orderItem->order->customer_id,
        'amount'      => $orderItem->total ?: 1000,
        'status'      => PaymentStatusEnum::COMPLETED,
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_item_id'              => $orderItem->id,
        'order_id'                   => $orderItem->order_id,
        'customer_id'                => $orderItem->order->customer_id,
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        'provisioning_plan'          => [
            'version'   => 1,
            'providers' => [['provider' => 'ims', 'applicable' => true, 'readiness' => 'ready']],
            'status'    => ProvisioningStatusEnum::READY->value,
        ],
    ]);

    $attempts = app(ProvisioningAttemptService::class);
    $attempt  = $attempts->queue($enrollment, ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::IMS);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    $enrollment->refresh();
    $firstData = data_get($enrollment->provisioning_data, 'providers.ims.data');

    expect($attempt->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and($enrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($firstData['course_code'])->toBe('IMS-E2E-76')
        ->and($firstData['ims_student_id'])->toBeInt()
        ->and($firstData['ims_enrollment_id'])->toBeInt();

    $retry = $attempts->queue($enrollment, ProvisioningTriggerEnum::RETRY,
        provider: ProvisioningProviderEnum::IMS);
    (new ProvisionEnrollmentProviderJob($retry->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    expect($retry->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and(data_get($enrollment->fresh()->provisioning_data, 'providers.ims.data.ims_student_id'))
        ->toBe($firstData['ims_student_id'])
        ->and(data_get($enrollment->fresh()->provisioning_data, 'providers.ims.data.ims_enrollment_id'))
        ->toBe($firstData['ims_enrollment_id']);
});

it('provisions Moodle Quiz with stable simulated references through the queued lifecycle', function (): void {
    app()->instance(
        MoodleClientContract::class,
        new FakeMoodleService(app(SettingsService::class)),
    );
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::DIRECT_DOWNLOAD,
        'details_json'    => ['moodle_quiz_course_id' => 654],
    ]);
    $orderItem = OrderItem::factory()->create([
        'product_delivery_option_id' => $option->id,
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_item_id'              => $orderItem->id,
        'order_id'                   => $orderItem->order_id,
        'customer_id'                => $orderItem->order->customer_id,
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        'provisioning_plan'          => [
            'version'   => 1,
            'providers' => [['provider' => 'moodle_quiz', 'applicable' => true, 'readiness' => 'ready']],
            'status'    => ProvisioningStatusEnum::READY->value,
        ],
    ]);

    $attempts = app(ProvisioningAttemptService::class);
    $attempt  = $attempts->queue($enrollment, ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::MOODLE_QUIZ);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    $enrollment->refresh();
    $firstData = data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data');

    expect($attempt->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and($enrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($firstData['moodle_user_id'])->toBe(1000 + $enrollment->customer_id)
        ->and($firstData['moodle_course_id'])->toBe(654)
        ->and($firstData)->not->toHaveKey('raw_payload');

    $retry = $attempts->queue($enrollment, ProvisioningTriggerEnum::RETRY,
        provider: ProvisioningProviderEnum::MOODLE_QUIZ);
    (new ProvisionEnrollmentProviderJob($retry->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    expect($retry->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and(data_get($enrollment->fresh()->provisioning_data, 'providers.moodle_quiz.data.moodle_user_id'))
        ->toBe($firstData['moodle_user_id']);
});

it('provisions SpotPlayer with stable simulated references through the queued lifecycle', function (): void {
    app()->instance(SpotPlayerClientContract::class, new FakeSpotPlayerService());
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER,
        'details_json'    => ['spot_id' => 'SPOT-E2E-79'],
    ]);
    $orderItem = OrderItem::factory()->create([
        'product_delivery_option_id' => $option->id,
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_item_id'              => $orderItem->id,
        'order_id'                   => $orderItem->order_id,
        'customer_id'                => $orderItem->order->customer_id,
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        'provisioning_plan'          => [
            'version'   => 1,
            'providers' => [['provider' => 'spotplayer', 'applicable' => true, 'readiness' => 'ready']],
            'status'    => ProvisioningStatusEnum::READY->value,
        ],
    ]);

    $attempts = app(ProvisioningAttemptService::class);
    $attempt  = $attempts->queue($enrollment, ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::SPOTPLAYER);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    $enrollment->refresh();
    $firstData = data_get($enrollment->provisioning_data, 'providers.spotplayer.data');

    expect($attempt->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and($enrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($firstData['spot_id'])->toBe('SPOT-E2E-79')
        ->and($firstData['license_key'])->toBeString()
        ->and($firstData['player_url'])->toBe('https://app.spotplayer.ir/player/SPOT-E2E-79/')
        ->and($firstData)->not->toHaveKey('raw');

    $retry = $attempts->queue($enrollment, ProvisioningTriggerEnum::RETRY,
        provider: ProvisioningProviderEnum::SPOTPLAYER);
    (new ProvisionEnrollmentProviderJob($retry->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    expect($retry->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and(data_get($enrollment->fresh()->provisioning_data, 'providers.spotplayer.data.license_key'))
        ->toBe($firstData['license_key'])
        ->and(data_get($enrollment->fresh()->provisioning_data, 'providers.spotplayer.data.player_url'))
        ->toBe($firstData['player_url']);
});

it('provisions BBB with stable simulated meeting data through the queued lifecycle', function (): void {
    app()->instance(BbbClientContract::class, new FakeBbbService());
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::LIVE_SESSION_BBB,
        'details_json'    => ['nili_room_id' => 'NILI-E2E-80'],
    ]);
    $orderItem = OrderItem::factory()->create([
        'product_delivery_option_id' => $option->id,
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_item_id'              => $orderItem->id,
        'order_id'                   => $orderItem->order_id,
        'customer_id'                => $orderItem->order->customer_id,
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        'provisioning_plan'          => [
            'version'   => 1,
            'providers' => [['provider' => 'bbb', 'applicable' => true, 'readiness' => 'ready']],
            'status'    => ProvisioningStatusEnum::READY->value,
        ],
    ]);

    $attempts = app(ProvisioningAttemptService::class);
    $attempt  = $attempts->queue($enrollment, ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::BBB);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    $enrollment->refresh();
    $firstData = data_get($enrollment->provisioning_data, 'providers.bbb.data');
    $joinUrl   = app(GetJoinUrlAction::class)->handle($enrollment)->url;

    expect($attempt->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and($enrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($firstData)->toBe(['meeting_id' => 'NILI-E2E-80'])
        ->and($joinUrl)->toContain('meetingID=NILI-E2E-80');

    $retry = $attempts->queue($enrollment, ProvisioningTriggerEnum::RETRY,
        provider: ProvisioningProviderEnum::BBB);
    (new ProvisionEnrollmentProviderJob($retry->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    expect($retry->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and(data_get($enrollment->fresh()->provisioning_data, 'providers.bbb.data.meeting_id'))
        ->toBe($firstData['meeting_id']);
});

it('provisions Skyroom with stable simulated room and customer data through the queued lifecycle', function (): void {
    app()->instance(SkyroomClientContract::class, new FakeSkyroomService());
    $option = ProductDeliveryOption::factory()->create([
        'delivery_method' => DeliveryMethodEnum::LIVE_SESSION_SKYROOM,
        'details_json'    => ['room_id' => 8101],
    ]);
    $orderItem = OrderItem::factory()->create([
        'product_delivery_option_id' => $option->id,
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_item_id'              => $orderItem->id,
        'order_id'                   => $orderItem->order_id,
        'customer_id'                => $orderItem->order->customer_id,
        'product_delivery_option_id' => $option->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
        'provisioning_plan'          => [
            'version'   => 1,
            'providers' => [['provider' => 'skyroom', 'applicable' => true, 'readiness' => 'ready']],
            'status'    => ProvisioningStatusEnum::READY->value,
        ],
    ]);

    $attempts = app(ProvisioningAttemptService::class);
    $attempt  = $attempts->queue($enrollment, ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::SKYROOM);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    $enrollment->refresh();
    $firstData = data_get($enrollment->provisioning_data, 'providers.skyroom.data');
    $joinUrl   = app(GetJoinUrlAction::class)->handle($enrollment)->url;

    expect($attempt->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and($enrollment->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY)
        ->and($firstData)->toHaveKeys(['room_id', 'skyroom_user_id'])
        ->and($firstData['room_id'])->toBe(8101)
        ->and($firstData['skyroom_user_id'])->toBeInt()
        ->and($joinUrl)->toContain('room_id=8101')
        ->and($joinUrl)->toContain('user_id=user-'.$enrollment->customer_id);

    $retry = $attempts->queue($enrollment, ProvisioningTriggerEnum::RETRY,
        provider: ProvisioningProviderEnum::SKYROOM);
    (new ProvisionEnrollmentProviderJob($retry->id))->handle($attempts, app(ProvisioningProviderRegistry::class));

    expect($retry->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and(data_get($enrollment->fresh()->provisioning_data, 'providers.skyroom.data'))
        ->toBe($firstData);
});

it('runs SpotPlayer through the provider boundary and stores only safe references', function (): void {
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        'provisioning_plan' => [
            'version'     => 1,
            'providers'   => [['provider' => 'spotplayer', 'applicable' => true, 'readiness' => 'ready']],
            'status'      => ProvisioningStatusEnum::READY->value,
            'resolved_at' => now()->toISOString(),
        ],
    ]);

    $provider = $this->mock(SpotPlayerProvisioningProvider::class);
    $provider->shouldReceive('provision')->once()->andReturn([
        'spot_id' => 'SPOT-1', 'license_key' => 'LIC-1', 'player_url' => 'https://player.test/1',
        'raw'     => ['secret' => 'x'],
    ]);

    $attempt = app(ProvisioningAttemptService::class)->queue($enrollment, ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::SPOTPLAYER);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle(app(ProvisioningAttemptService::class),
        app(ProvisioningProviderRegistry::class));

    $enrollment->refresh();
    expect($attempt->refresh()->status->value)->toBe('succeeded')
        ->and(data_get($enrollment->provisioning_data, 'providers.spotplayer.data.license_key'))->toBe('LIC-1')
        ->and(data_get($enrollment->provisioning_data, 'providers.spotplayer.data'))->not->toHaveKey('raw');
});

it('runs Moodle Quiz through the provider boundary', function (): void {
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
        'provisioning_plan' => [
            'version'     => 1,
            'providers'   => [['provider' => 'moodle_quiz', 'applicable' => true, 'readiness' => 'ready']],
            'status'      => ProvisioningStatusEnum::READY->value,
            'resolved_at' => now()->toISOString(),
        ],
    ]);

    $provider = $this->mock(MoodleQuizProvisioningProvider::class);
    $provider->shouldReceive('provision')->once()->andReturn([
        'moodle_user_id' => 42, 'moodle_username' => 'quiz-user', 'moodle_course_id' => 99,
    ]);

    $attempt = app(ProvisioningAttemptService::class)->queue($enrollment, ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::MOODLE_QUIZ);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle(app(ProvisioningAttemptService::class),
        app(ProvisioningProviderRegistry::class));

    $enrollment->refresh();
    expect($attempt->refresh()->status->value)->toBe('succeeded')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data.moodle_course_id'))->toBe(99);
});

it('queues access reconciliation for a provider with an adapter capability', function (): void {
    Queue::fake();
    $enrollment = reconciliationEnrollment('moodle', ['moodle_user_id' => 42, 'moodle_course_id' => 99]);
    $staffId    = Staff::factory()->create()->id;

    app(ProvisioningAttemptService::class)->recordAccessReconciliation($enrollment, [
        'reason'          => 'Dates corrected', 'status' => 'active', 'access_start_date' => '2026-01-01',
        'access_end_date' => '2026-12-31',
    ], $staffId);

    $attempt = $enrollment->provisioningAttempts()->latest('id')->first();
    expect($attempt?->status)->toBe(ProvisioningAttemptStatusEnum::QUEUED)
        ->and($attempt?->staff_id)->toBe($staffId)
        ->and(data_get($enrollment->fresh()->provisioning_data, 'reconciliation.status'))->toBe('in_progress');
    Queue::assertPushed(ProvisionEnrollmentProviderJob::class);
});

it('leaves unsupported provider access changes for manual action', function (): void {
    Queue::fake();
    $enrollment = reconciliationEnrollment('skyroom', ['room_id' => 10, 'skyroom_user_id' => 42]);
    $staffId    = Staff::factory()->create()->id;

    app(ProvisioningAttemptService::class)->recordAccessReconciliation($enrollment, [
        'reason' => 'Suspended by staff', 'status' => 'suspended',
    ], $staffId);

    $attempt = $enrollment->provisioningAttempts()->latest('id')->first();
    expect($attempt?->status)->toBe(ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($attempt?->failure_message)->toContain('Suspended by staff')
        ->and($enrollment->fresh()->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE)
        ->and(data_get($enrollment->fresh()->provisioning_data, 'reconciliation.status'))
        ->toBe('manual_action_required');
    Queue::assertNotPushed(ProvisionEnrollmentProviderJob::class);
});

it('preserves partial reconciliation health when one provider remains manual', function (): void {
    Queue::fake();
    $enrollment = reconciliationEnrollment('moodle', ['moodle_user_id' => 42, 'moodle_course_id' => 99]);
    $enrollment->update([
        'provisioning_plan' => [
            'version'   => 1,
            'providers' => [
                ['provider' => 'moodle', 'applicable' => true, 'readiness' => 'ready'],
                ['provider' => 'skyroom', 'applicable' => true, 'readiness' => 'ready'],
            ],
        ],
        'provisioning_data' => [
            'providers' => [
                'moodle'  => ['status' => 'success', 'data' => ['moodle_user_id' => 42, 'moodle_course_id' => 99]],
                'skyroom' => ['status' => 'success', 'data' => ['room_id' => 10, 'skyroom_user_id' => 42]],
            ],
        ],
    ]);

    app(ProvisioningAttemptService::class)->recordAccessReconciliation($enrollment, [
        'reason' => 'Reconcile mixed providers', 'status' => 'active',
    ]);
    $moodleAttempt  = $enrollment->provisioningAttempts()->where('provider', 'moodle')->latest('id')->firstOrFail();
    $runningAttempt = app(ProvisioningAttemptService::class)->start($moodleAttempt->id);
    app(ProvisioningAttemptService::class)->succeed($runningAttempt,
        ['moodle_user_id' => 42, 'moodle_course_id' => 99]);

    expect(data_get($enrollment->fresh()->provisioning_data, 'reconciliation.status'))->toBe('manual_action_required');
});

it('keeps local access authoritative when reconciliation fails', function (): void {
    Queue::fake();
    $enrollment = reconciliationEnrollment('moodle', ['moodle_user_id' => 42, 'moodle_course_id' => 99]);
    $staffId    = Staff::factory()->create()->id;
    $moodle     = $this->mock(App\Services\Integrations\MoodleService::class);

    $attemptService = app(ProvisioningAttemptService::class);
    $attemptService->recordAccessReconciliation($enrollment, ['reason' => 'Date correction', 'status' => 'active'],
        $staffId);
    $attempt = $enrollment->provisioningAttempts()->latest('id')->firstOrFail();

    $runningAttempt = $attemptService->start($attempt->id);
    expect($runningAttempt)->not->toBeNull();
    $attemptService->fail($runningAttempt ?? throw new RuntimeException('Attempt did not start.'),
        new RuntimeException('remote unavailable'));

    expect($enrollment->fresh()->enrollment_status)->toBe(EnrollmentStatusEnum::ACTIVE)
        ->and(data_get($enrollment->fresh()->provisioning_data, 'reconciliation.status'))->toBe('failed');
});
