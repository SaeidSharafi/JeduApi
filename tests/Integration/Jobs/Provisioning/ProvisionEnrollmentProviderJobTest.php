<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Enrollment;
use App\Services\Provisioning\Providers\MoodleProvisioningProvider;
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
