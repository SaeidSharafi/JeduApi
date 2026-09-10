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
