<?php

declare(strict_types=1);

use App\Enums\EnrollmentRevocationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Jobs\Provisioning\RevokeEnrollmentProviderJob;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProvisioningAttempt;
use App\Services\Integrations\MoodleService;
use App\Services\Provisioning\EnrollmentRevocationService;
use App\Services\Provisioning\ProvisioningAttemptService;
use App\Services\Provisioning\ProvisioningProviderRegistry;

covers(RevokeEnrollmentProviderJob::class);

/** @param list<string> $providers */
function revocationJobEnrollment(array $providers): Enrollment
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
            'data'   => $provider === 'moodle'
                ? ['moodle_user_id' => 11, 'moodle_course_id' => 22]
                : ['ims_student_id' => 55, 'ims_enrollment_id' => 66],
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

it('revokes Moodle access through the provider and settles the enrollment', function (): void {
    $enrollment = revocationJobEnrollment(['moodle']);
    $attemptIds = app(EnrollmentRevocationService::class)->begin($enrollment);

    $moodle = $this->mock(MoodleService::class);
    $moodle->shouldReceive('isEnabled')->andReturnTrue();
    $moodle->shouldReceive('assertConfigured');
    $moodle->shouldReceive('unenrollUser')->once()->with(11, 22);

    (new RevokeEnrollmentProviderJob($attemptIds[0]))->handle(
        app(ProvisioningAttemptService::class),
        app(ProvisioningProviderRegistry::class),
        app(EnrollmentRevocationService::class),
    );

    $fresh = $enrollment->fresh();
    expect($fresh->revocation_status)->toBe(EnrollmentRevocationStatusEnum::REVOKED)
        ->and($fresh->enrollment_status)->toBe(EnrollmentStatusEnum::CANCELLED)
        ->and(ProvisioningAttempt::query()->findOrFail($attemptIds[0])->status)
        ->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED)
        ->and($fresh->provisioning_data['revocation']['providers']['moodle']['status'])->toBe('revoked');
});

it('marks an unsupported provider as manual work without calling it', function (): void {
    $enrollment = revocationJobEnrollment(['ims']);
    $attempt    = app(ProvisioningAttemptService::class)->queue(
        $enrollment,
        ProvisioningTriggerEnum::REVOCATION,
        provider: ProvisioningProviderEnum::IMS,
    );

    (new RevokeEnrollmentProviderJob($attempt->id))->handle(
        app(ProvisioningAttemptService::class),
        app(ProvisioningProviderRegistry::class),
        app(EnrollmentRevocationService::class),
    );

    expect($attempt->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and($enrollment->fresh()->revocation_status)->toBe(EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED);
});

it('surfaces an unrecoverable revocation failure as manual action', function (): void {
    $enrollment = revocationJobEnrollment(['moodle']);
    $attemptIds = app(EnrollmentRevocationService::class)->begin($enrollment);

    $moodle = $this->mock(MoodleService::class);
    $moodle->shouldReceive('isEnabled')->andReturnTrue();
    $moodle->shouldReceive('assertConfigured');
    $moodle->shouldReceive('unenrollUser')->andThrow(new UnrecoverableProvisioningException('Moodle rejected'));

    try {
        (new RevokeEnrollmentProviderJob($attemptIds[0]))->handle(
            app(ProvisioningAttemptService::class),
            app(ProvisioningProviderRegistry::class),
            app(EnrollmentRevocationService::class),
        );
    } catch (UnrecoverableProvisioningException) {
        // The job rethrows after recording the manual outcome.
    }

    expect($enrollment->fresh()->revocation_status)->toBe(EnrollmentRevocationStatusEnum::MANUAL_ACTION_REQUIRED)
        ->and(ProvisioningAttempt::query()->findOrFail($attemptIds[0])->status)
        ->toBe(ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED);
});

it('does nothing when the attempt is no longer queueable', function (): void {
    $enrollment = revocationJobEnrollment(['moodle']);
    $attemptIds = app(EnrollmentRevocationService::class)->begin($enrollment);
    $attempt    = ProvisioningAttempt::query()->findOrFail($attemptIds[0]);
    $attempt->forceFill(['status' => ProvisioningAttemptStatusEnum::SUCCEEDED])->save();

    $moodle = $this->mock(MoodleService::class);
    $moodle->shouldNotReceive('unenrollUser');

    (new RevokeEnrollmentProviderJob($attemptIds[0]))->handle(
        app(ProvisioningAttemptService::class),
        app(ProvisioningProviderRegistry::class),
        app(EnrollmentRevocationService::class),
    );

    expect($attempt->refresh()->status)->toBe(ProvisioningAttemptStatusEnum::SUCCEEDED);
});
