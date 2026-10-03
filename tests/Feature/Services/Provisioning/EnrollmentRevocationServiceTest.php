<?php

declare(strict_types=1);

use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Jobs\Provisioning\RevokeEnrollmentProviderJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProvisioningAttempt;
use App\Services\Provisioning\EnrollmentRevocationService;
use App\Services\Provisioning\ProvisioningAttemptService;
use Illuminate\Support\Facades\Queue;

covers(EnrollmentRevocationService::class);

/** @param list<string> $providers */
function revocationEnrollment(array $providers): Enrollment
{
    $order = Order::factory()->create();
    $item  = OrderItem::factory()->create([
        'order_id' => $order->id, 'status' => OrderItemStatusEnum::COMPLETED, 'price' => 100000, 'total' => 100000,
    ]);
    $enrollment = Enrollment::factory()->create([
        'order_item_id'              => $item->id,
        'order_id'                   => $order->id,
        'customer_id'                => $order->customer_id,
        'product_delivery_option_id' => $item->product_delivery_option_id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);

    $planProviders = [];
    $data          = [];
    foreach ($providers as $provider) {
        $planProviders[] = [
            'provider' => $provider, 'applicable' => true, 'readiness' => 'ready', 'configuration_issue' => null,
        ];
        $data['providers'][$provider] = [
            'status' => 'success',
            'data'   => match ($provider) {
                'moodle'      => ['moodle_user_id' => 11, 'moodle_course_id' => 22],
                'moodle_quiz' => ['moodle_user_id' => 33, 'moodle_course_id' => 44],
                default       => ['ims_student_id' => 55, 'ims_enrollment_id' => 66],
            },
        ];
    }

    $enrollment->update([
        'provisioning_plan' => [
            'version' => 1, 'providers' => $planProviders, 'status' => 'healthy', 'resolved_at' => now()->toISOString(),
        ],
        'provisioning_data'   => $data,
        'provisioning_status' => ProvisioningStatusEnum::HEALTHY,
    ]);

    return $enrollment->fresh();
}

function revocationStart(EnrollmentRevocationService $service, Enrollment $enrollment, string $provider): ProvisioningAttempt
{
    $attemptId = ProvisioningAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('provider', $provider)
        ->where('trigger', ProvisioningTriggerEnum::REVOCATION->value)
        ->where('status', ProvisioningAttemptStatusEnum::QUEUED->value)
        ->latest('id')
        ->sole()
        ->id;

    return app(ProvisioningAttemptService::class)->start($attemptId);
}

beforeEach(function (): void {
    Queue::fake([RevokeEnrollmentProviderJob::class]);
    $this->service = app(EnrollmentRevocationService::class);
});

it('queues a revocation attempt per supported provider and marks the enrollment revocation pending', function (): void {
    $enrollment = revocationEnrollment(['moodle']);

    $attemptIds = $this->service->begin($enrollment);
    $this->service->dispatchAttempts($attemptIds);

    expect($attemptIds)->toHaveCount(1);
    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);

    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE->value,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
        'status'        => ProvisioningAttemptStatusEnum::QUEUED->value,
    ]);
    Queue::assertPushed(RevokeEnrollmentProviderJob::class, 1);
});

it('records explicit manual work for a provider without a revocation API', function (): void {
    $enrollment = revocationEnrollment(['ims']);

    $attemptIds = $this->service->begin($enrollment);

    expect($attemptIds)->toBe([]);
    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);

    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::IMS->value,
        'status'        => ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED->value,
    ]);
    Queue::assertNothingPushed();
});

it('completes immediately when no provider granted external access', function (): void {
    $enrollment = revocationEnrollment([]);

    $attemptIds = $this->service->begin($enrollment);

    expect($attemptIds)->toBe([]);
    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::REVOKED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::CANCELLED);
    $this->assertDatabaseCount('provisioning_attempts', 0);
});

it('skips a planned provider that never granted external access', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    // Planned, but provisioning never ran, so nothing external can be revoked.
    $enrollment->update(['provisioning_data' => []]);

    $attemptIds = $this->service->begin($enrollment);

    expect($attemptIds)->toBe([]);
    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::REVOKED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::CANCELLED);
    $this->assertDatabaseCount('provisioning_attempts', 0);
});

it('marks the enrollment revoked and cancelled only once every required provider succeeded', function (): void {
    $enrollment = revocationEnrollment(['moodle', 'moodle_quiz']);
    $this->service->begin($enrollment);

    $moodle = revocationStart($this->service, $enrollment, 'moodle');
    $this->service->succeed($moodle, ['moodle_user_id' => 11, 'moodle_course_id' => 22]);

    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);

    $quiz = revocationStart($this->service, $enrollment, 'moodle_quiz');
    $this->service->succeed($quiz, ['moodle_user_id' => 33, 'moodle_course_id' => 44]);

    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::REVOKED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::CANCELLED)
        ->and($fresh->provisioning_data['revocation']['providers']['moodle']['status'])->toBe('revoked');

    // The already-succeeded provider is not reverted by unrelated state.
    expect(ProvisioningAttempt::query()
        ->where('provider', ProvisioningProviderEnum::MOODLE->value)
        ->where('status', ProvisioningAttemptStatusEnum::SUCCEEDED->value)
        ->exists())->toBeTrue();
});

it('keeps a failed revocation visible, retryable, and blocked', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');

    $this->service->fail($attempt, new RuntimeException('Moodle unavailable'));

    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::FAILED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);
    $this->assertDatabaseHas('provisioning_attempts', [
        'id'              => $attempt->id,
        'status'          => ProvisioningAttemptStatusEnum::FAILED->value,
        'retryable'       => true,
        'failure_message' => 'Moodle unavailable',
    ]);
});

it('retries only providers whose revocation has not succeeded', function (): void {
    $enrollment = revocationEnrollment(['moodle', 'moodle_quiz']);
    $this->service->begin($enrollment);

    $moodle = revocationStart($this->service, $enrollment, 'moodle');
    $this->service->succeed($moodle, ['moodle_user_id' => 11, 'moodle_course_id' => 22]);

    $quiz = revocationStart($this->service, $enrollment, 'moodle_quiz');
    $this->service->fail($quiz, new RuntimeException('Quiz provider failed'));

    $attemptIds = $this->service->retry($enrollment);

    expect($attemptIds)->toHaveCount(1);
    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE_QUIZ->value,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
        'status'        => ProvisioningAttemptStatusEnum::QUEUED->value,
    ]);
    // Moodle already succeeded, so no second Moodle attempt is queued.
    expect(ProvisioningAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('provider', ProvisioningProviderEnum::MOODLE->value)
        ->where('trigger', ProvisioningTriggerEnum::REVOCATION->value)
        ->count())->toBe(1);
});

it('never reopens a completed revocation when fail or retry is called again', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');
    $this->service->succeed($attempt, ['moodle_user_id' => 11, 'moodle_course_id' => 22]);

    $this->service->retry($enrollment);
    $this->service->fail($attempt, new RuntimeException('late failure'));

    expect($enrollment->fresh()->revocation_status)->toBe(EnrollmentRevocationStatusEnum::REVOKED);
});

it('confirms an externally unsupported revocation manually', function (): void {
    $staff      = App\Models\Staff::factory()->create();
    $enrollment = revocationEnrollment(['ims']);
    $this->service->begin($enrollment);

    $this->service->confirmManually($enrollment, $staff->id, 'Removed in IMS by hand');

    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::REVOKED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::CANCELLED);
    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::IMS->value,
        'status'        => ProvisioningAttemptStatusEnum::SUCCEEDED->value,
        'staff_id'      => $staff->id,
    ]);
});

it('queues the revocation that begin could not create when a provisioning attempt is in flight', function (): void {
    $enrollment = revocationEnrollment(['moodle']);

    // A payment provisioning attempt is already active when the refund demands
    // revocation, so begin() waits instead of creating a competing attempt.
    $provisioning = app(ProvisioningAttemptService::class)->queue($enrollment, ProvisioningTriggerEnum::PAYMENT);
    $running      = app(ProvisioningAttemptService::class)->start($provisioning->id);

    expect($this->service->begin($enrollment))->toBe([]);
    expect($enrollment->fresh()->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING);

    // The provisioning succeeds and grants access after the refund; revocation
    // must now actually be queued rather than silently skipped.
    app(ProvisioningAttemptService::class)->succeed(
        $running,
        ['moodle_user_id' => 11, 'moodle_course_id' => 22],
    );

    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE->value,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
        'status'        => ProvisioningAttemptStatusEnum::QUEUED->value,
    ]);
    Queue::assertPushed(RevokeEnrollmentProviderJob::class, 1);
});

it('waits for an in-flight provisioning attempt that has not produced an outcome yet', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $enrollment->update(['provisioning_data' => []]);

    // The attempt has started but has not written an outcome, so access may
    // still be granted: revocation must wait rather than complete.
    $provisioning = app(ProvisioningAttemptService::class)->queue($enrollment, ProvisioningTriggerEnum::PAYMENT);
    app(ProvisioningAttemptService::class)->start($provisioning->id);

    expect($this->service->begin($enrollment))->toBe([]);
    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);
});

it('skips an already revoked provider while still queueing the remaining providers', function (): void {
    $enrollment = revocationEnrollment(['moodle', 'moodle_quiz']);

    // Moodle was already revoked by an earlier run; the quiz provider still needs it.
    ProvisioningAttempt::query()->create([
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION,
        'status'        => ProvisioningAttemptStatusEnum::SUCCEEDED,
        'sequence'      => 1,
        'retryable'     => false,
        'succeeded_at'  => now(),
    ]);

    $attemptIds = $this->service->begin($enrollment);

    expect($attemptIds)->toHaveCount(1);
    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE_QUIZ->value,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
        'status'        => ProvisioningAttemptStatusEnum::QUEUED->value,
    ]);
    expect($enrollment->fresh()->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING);
});

it('waits for one provider in flight while queueing revocation for another', function (): void {
    $enrollment = revocationEnrollment(['moodle', 'moodle_quiz']);

    // Moodle is still provisioning, so begin() must wait for it and revoke the quiz now.
    $inFlight = app(ProvisioningAttemptService::class)->queue(
        $enrollment,
        ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::MOODLE,
    );
    app(ProvisioningAttemptService::class)->start($inFlight->id);

    $attemptIds = $this->service->begin($enrollment);

    expect($attemptIds)->toHaveCount(1);
    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE_QUIZ->value,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
        'status'        => ProvisioningAttemptStatusEnum::QUEUED->value,
    ]);
});

it('reports pending while a provider is in flight even though another needs manual work', function (): void {
    $enrollment = revocationEnrollment(['moodle', 'ims']);

    // One provider may still grant access; another needs a human. Waiting wins:
    // an in-flight attempt is not a completed revocation, so the state is pending.
    $inFlight = app(ProvisioningAttemptService::class)->queue(
        $enrollment,
        ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::MOODLE,
    );
    app(ProvisioningAttemptService::class)->start($inFlight->id);

    expect($this->service->begin($enrollment))->toBe([]);
    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);
});

it('returns a failed revocation to pending when a retry is queued', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');
    $this->service->fail($attempt, new RuntimeException('Moodle unavailable'));

    expect($enrollment->fresh()->revocation_status)->toBe(EnrollmentRevocationStatusEnum::FAILED);

    expect($this->service->retry($enrollment))->toHaveCount(1);

    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);
});

it('keeps an unsupported provider in manual work when a retry is requested', function (): void {
    $enrollment = revocationEnrollment(['ims']);
    $this->service->begin($enrollment);

    expect($this->service->retry($enrollment))->toBe([]);

    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);
    $this->assertDatabaseCount('provisioning_attempts', 1);
});

it('records the unsupported-provider work item with its full audit detail', function (): void {
    $enrollment = revocationEnrollment(['ims']);

    $this->service->begin($enrollment);

    $attempt = ProvisioningAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('provider', ProvisioningProviderEnum::IMS->value)
        ->where('trigger', ProvisioningTriggerEnum::REVOCATION->value)
        ->sole();

    expect((int) $attempt->sequence)->toBe(1)
        ->and($attempt->status)->toBe(ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($attempt->retryable)->toBeFalse()
        ->and($attempt->failure_message)->toBe(__('messages.provisioning.revocation_not_supported'))
        ->and($attempt->failure_metadata)->toBe(['kind' => 'revocation', 'provider_supported' => false])
        ->and($attempt->failed_at)->not->toBeNull()
        ->and($attempt->manual_action_required_at)->not->toBeNull();
});

it('records a fully audited succeeding attempt when staff confirm manual revocation', function (): void {
    $staff      = App\Models\Staff::factory()->create();
    $enrollment = revocationEnrollment(['ims']);
    $this->service->begin($enrollment);

    $this->service->confirmManually($enrollment, $staff->id, 'Removed in IMS by hand');

    $attempt = ProvisioningAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('provider', ProvisioningProviderEnum::IMS->value)
        ->where('status', ProvisioningAttemptStatusEnum::SUCCEEDED->value)
        ->sole();

    expect($attempt->retryable)->toBeFalse()
        ->and($attempt->staff_id)->toBe($staff->id)
        ->and($attempt->succeeded_at)->not->toBeNull()
        ->and($attempt->failure_metadata)->toEqual([
            'kind'                => 'revocation',
            'manual_confirmation' => true,
            'reason'              => 'Removed in IMS by hand',
        ])
        ->and($enrollment->fresh()->revocation_status)->toBe(EnrollmentRevocationStatusEnum::REVOKED);
});

it('omits the manual reason from the audit when staff give none', function (): void {
    $staff      = App\Models\Staff::factory()->create();
    $enrollment = revocationEnrollment(['ims']);
    $this->service->begin($enrollment);

    $this->service->confirmManually($enrollment, $staff->id);

    $attempt = ProvisioningAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('provider', ProvisioningProviderEnum::IMS->value)
        ->where('status', ProvisioningAttemptStatusEnum::SUCCEEDED->value)
        ->sole();

    expect($attempt->failure_metadata)->toEqual([
        'kind'                => 'revocation',
        'manual_confirmation' => true,
    ]);
});

it('confirms only the providers whose revocation is still outstanding', function (): void {
    $staff      = App\Models\Staff::factory()->create();
    $enrollment = revocationEnrollment(['moodle', 'ims']);
    $this->service->begin($enrollment);

    // Moodle was revoked successfully; only IMS still needs manual work.
    $moodle = revocationStart($this->service, $enrollment, 'moodle');
    $this->service->succeed($moodle, ['moodle_user_id' => 11, 'moodle_course_id' => 22]);

    $this->service->confirmManually($enrollment, $staff->id, 'Removed in IMS by hand');

    expect(ProvisioningAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('provider', ProvisioningProviderEnum::MOODLE->value)
        ->where('trigger', ProvisioningTriggerEnum::REVOCATION->value)
        ->count())->toBe(1)
        ->and($enrollment->fresh()->revocation_status)->toBe(EnrollmentRevocationStatusEnum::REVOKED);
});

it('stores only the canonical safe references when a provider revocation succeeds', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');

    $this->service->succeed($attempt, [
        'moodle_user_id'   => 11,
        'moodle_course_id' => 22,
        'revoked_at'       => '2026-09-10T00:00:00+00:00',
        'api_token'        => 'super-secret',
        'password'         => 'hunter2',
    ]);

    $record = $enrollment->fresh()->provisioning_data['revocation']['providers']['moodle'];

    expect($record['status'])->toBe('revoked')
        ->and((int) $record['attempt_sequence'])->toBe((int) $attempt->sequence)
        ->and($record['data'])->toEqual([
            'moodle_user_id'   => 11,
            'moodle_course_id' => 22,
            'revoked_at'       => '2026-09-10T00:00:00+00:00',
        ])
        ->and($record['revoked_at'])->not->toBeNull()
        ->and($attempt->fresh()->retryable)->toBeFalse()
        ->and($attempt->fresh()->succeeded_at)->not->toBeNull();
});

it('ignores a success report for an attempt that was never started', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);

    $queued = ProvisioningAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('provider', ProvisioningProviderEnum::MOODLE->value)
        ->where('status', ProvisioningAttemptStatusEnum::QUEUED->value)
        ->sole();

    $this->service->succeed($queued, ['moodle_user_id' => 11, 'moodle_course_id' => 22]);

    expect($queued->fresh()->status)->toBe(ProvisioningAttemptStatusEnum::QUEUED)
        ->and($enrollment->fresh()->provisioning_data)->not->toHaveKey('revocation');
});

it('ignores a failure report for an attempt that was never started', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);

    $queued = ProvisioningAttempt::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('provider', ProvisioningProviderEnum::MOODLE->value)
        ->where('status', ProvisioningAttemptStatusEnum::QUEUED->value)
        ->sole();

    $this->service->fail($queued, new RuntimeException('Moodle unavailable'));

    expect($queued->fresh()->status)->toBe(ProvisioningAttemptStatusEnum::QUEUED);
});

it('marks the attempt as manual work when the provider needs a human', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');

    $this->service->fail($attempt, new RuntimeException('Moodle rejected the request'), true);

    expect($attempt->fresh()->status)->toBe(ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($attempt->fresh()->manual_action_required_at)->not->toBeNull()
        ->and($enrollment->fresh()->revocation_status)
        ->toBe(EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED);
});

it('leaves a completed revocation untouched when a late attempt succeeds', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');

    $enrollment->forceFill([
        'revocation_status' => EnrollmentRevocationStatusEnum::REVOKED,
        'enrollment_status' => EnrollmentStatusEnum::CANCELLED,
    ])->save();

    $this->service->succeed($attempt, ['moodle_user_id' => 11, 'moodle_course_id' => 22]);

    expect($enrollment->fresh()->provisioning_data)->not->toHaveKey('revocation');
});

it('records the failure audit with code, metadata and a truncated message', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');

    $this->service->fail(
        $attempt,
        new RuntimeException(str_repeat('x', 1500), 503),
        false,
        ['http_status' => 503, 'endpoint' => '/webservice/rest/server.php', 'errorcode' => 'webserviceerror'],
    );

    $failed = $attempt->fresh();
    expect($failed->status)->toBe(ProvisioningAttemptStatusEnum::FAILED)
        ->and($failed->retryable)->toBeTrue()
        ->and($failed->failure_code)->toBe('503')
        ->and(mb_strlen((string) $failed->failure_message))->toBe(1000)
        ->and($failed->failure_message)->toBe(str_repeat('x', 1000))
        ->and($failed->failed_at)->not->toBeNull()
        ->and($failed->manual_action_required_at)->toBeNull()
        ->and($failed->failure_metadata)->toEqual([
            'kind'        => 'revocation',
            'http_status' => 503,
            'endpoint'    => '/webservice/rest/server.php',
            'errorcode'   => 'webserviceerror',
        ]);
});

it('filters absent provider metadata out of the failure audit', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');

    $this->service->fail($attempt, new RuntimeException('Moodle unavailable'));

    expect($attempt->fresh()->failure_metadata)->toBe(['kind' => 'revocation']);
});

it('schedules a retry, marks the attempt retry-scheduled and returns the enrollment to pending', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');

    // A previously failed revocation being retried: re-deriving the state from
    // the attempt log must return it to pending rather than leave it failed.
    $enrollment->forceFill(['revocation_status' => EnrollmentRevocationStatusEnum::FAILED])->save();

    $this->service->scheduleRetry($attempt);

    $scheduled = $attempt->fresh();
    expect($scheduled->status)->toBe(ProvisioningAttemptStatusEnum::RETRY_SCHEDULED)
        ->and($scheduled->retry_scheduled_at)->not->toBeNull();

    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);
});

it('ignores a retry schedule for an attempt that is no longer running', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $this->service->begin($enrollment);
    $attempt = revocationStart($this->service, $enrollment, 'moodle');
    $this->service->succeed($attempt, ['moodle_user_id' => 11, 'moodle_course_id' => 22]);

    $this->service->scheduleRetry($attempt);

    expect($attempt->fresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED);
});

it('skips inapplicable and unknown plan entries while revoking the applicable providers', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $enrollment->update(['provisioning_plan' => [
        'version'   => 1, 'status' => 'healthy', 'resolved_at' => now()->toISOString(),
        'providers' => [
            ['provider' => 'moodle',  'applicable' => false, 'readiness' => 'ready', 'configuration_issue' => null],
            ['provider' => 'unknown', 'applicable' => true,  'readiness' => 'ready', 'configuration_issue' => null],
            ['provider' => 'moodle',  'applicable' => true,  'readiness' => 'ready', 'configuration_issue' => null],
        ],
    ]]);

    expect($this->service->begin($enrollment))->toHaveCount(1);

    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE->value,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
        'status'        => ProvisioningAttemptStatusEnum::QUEUED->value,
    ]);
});

it('ignores a provider that never granted access while revoking a later granted provider', function (): void {
    $enrollment = revocationEnrollment(['moodle', 'moodle_quiz']);
    $enrollment->update(['provisioning_data' => [
        'providers' => [
            'moodle'      => ['status' => 'failed', 'data' => ['moodle_user_id' => 11, 'moodle_course_id' => 22]],
            'moodle_quiz' => ['status' => 'success', 'data' => ['moodle_user_id' => 33, 'moodle_course_id' => 44]],
        ],
    ]]);

    expect($this->service->begin($enrollment))->toHaveCount(1);

    $this->assertDatabaseHas('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE_QUIZ->value,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
    ]);
    $this->assertDatabaseMissing('provisioning_attempts', [
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE->value,
        'trigger'       => ProvisioningTriggerEnum::REVOCATION->value,
    ]);
});

it('treats a revocable provider with no stored references as manual work', function (): void {
    $enrollment = revocationEnrollment(['moodle']);
    $enrollment->update(['provisioning_data' => [
        'providers' => ['moodle' => ['status' => 'success', 'data' => []]],
    ]]);

    expect($this->service->begin($enrollment))->toBe([]);
    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);
    Queue::assertNothingPushed();
});

it('stays pending, not failed, when one provider failed and another has no attempt yet', function (): void {
    $enrollment = revocationEnrollment(['moodle_quiz', 'moodle']);

    // The quiz provider is still provisioning, so begin() waits for it and creates
    // no revocation attempt for it; Moodle's revocation attempt then fails.
    $inFlight = app(ProvisioningAttemptService::class)->queue(
        $enrollment,
        ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::MOODLE_QUIZ,
    );
    app(ProvisioningAttemptService::class)->start($inFlight->id);

    $this->service->begin($enrollment);
    $moodle = revocationStart($this->service, $enrollment, 'moodle');
    $this->service->fail($moodle, new RuntimeException('Moodle unavailable'));

    // A provider that may still revoke keeps the purchase pending: the failure is
    // not final until every required provider has settled.
    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::PENDING)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);
});

it('reports manual work when a missing attempt precedes an unsupported provider', function (): void {
    $enrollment = revocationEnrollment(['moodle_quiz', 'ims']);

    // The quiz provider is still provisioning, so it can grant access later and
    // counts as required even though it has no revocation attempt yet.
    $inFlight = app(ProvisioningAttemptService::class)->queue(
        $enrollment,
        ProvisioningTriggerEnum::PAYMENT,
        provider: ProvisioningProviderEnum::MOODLE_QUIZ,
    );
    app(ProvisioningAttemptService::class)->start($inFlight->id);

    $this->service->begin($enrollment);

    expect($this->service->retry($enrollment))->toBe([]);
    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::SUSPENDED);
    $this->assertDatabaseCount('provisioning_attempts', 2);
});
