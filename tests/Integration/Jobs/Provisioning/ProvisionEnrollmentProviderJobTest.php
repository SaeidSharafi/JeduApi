<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Enrollment;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
use App\Services\Provisioning\Providers\MoodleQuizProvisioningProvider;
use App\Services\Provisioning\Providers\SpotPlayerProvisioningProvider;
use App\Services\Provisioning\ProvisioningAttemptService;
use App\Services\Provisioning\ProvisioningProviderRegistry;

it('runs Moodle through the provider boundary and activates the enrollment', function (): void {
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::PENDING_PROVISIONING,
    ]);
    $enrollment->update([
        'provisioning_plan' => [
            'version'   => 1,
            'providers' => [[
                'provider'            => 'moodle',
                'applicable'          => true,
                'readiness'           => 'ready',
                'configuration_issue' => null,
            ]],
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
        'spot_id' => 'SPOT-1', 'license_key' => 'LIC-1', 'player_url' => 'https://player.test/1', 'raw' => ['secret' => 'x'],
    ]);

    $attempt = app(ProvisioningAttemptService::class)->queue($enrollment, ProvisioningTriggerEnum::PAYMENT, provider: ProvisioningProviderEnum::SPOTPLAYER);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle(app(ProvisioningAttemptService::class), app(ProvisioningProviderRegistry::class));

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

    $attempt = app(ProvisioningAttemptService::class)->queue($enrollment, ProvisioningTriggerEnum::PAYMENT, provider: ProvisioningProviderEnum::MOODLE_QUIZ);
    (new ProvisionEnrollmentProviderJob($attempt->id))->handle(app(ProvisioningAttemptService::class), app(ProvisioningProviderRegistry::class));

    $enrollment->refresh();
    expect($attempt->refresh()->status->value)->toBe('succeeded')
        ->and(data_get($enrollment->provisioning_data, 'providers.moodle_quiz.data.moodle_course_id'))->toBe(99);
});
