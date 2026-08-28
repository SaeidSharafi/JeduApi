<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Enrollment;
use App\Models\Staff;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleQuizProvisioningProvider;
use App\Services\Provisioning\Providers\SpotPlayerProvisioningProvider;
use App\Services\Provisioning\ProvisioningAttemptService;
use App\Services\Provisioning\ProvisioningProviderRegistry;
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
        'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING,
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

it('runs SpotPlayer through the provider boundary and stores only safe references', function (): void {
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING,
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
        'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING,
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
