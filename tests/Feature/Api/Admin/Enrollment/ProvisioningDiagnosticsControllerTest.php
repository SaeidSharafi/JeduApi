<?php

declare(strict_types=1);

use App\Enums\PermissionEnum;
use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use Tests\Support\Traits\AuthTestTrait;

uses(AuthTestTrait::class);

it('returns safe diagnostics to enrollment viewers', function (): void {
    $enrollment = Enrollment::factory()->create();
    $enrollment->update([
        'provisioning_plan'   => ['version' => 1, 'providers' => [['provider' => 'moodle', 'applicable' => true, 'readiness' => 'ready']], 'status' => 'degraded'],
        'provisioning_status' => 'degraded',
        'provisioning_data'   => ['providers' => ['moodle' => ['status' => 'failed', 'last_error' => 'Moodle rejected test@example.com']]],
    ]);
    $this->authorized_user([PermissionEnum::ENROLLMENT_VIEW->value]);

    $this->getJson(route('api.v1.admin.enrollments.provisioning.diagnostics', $enrollment))
        ->assertOk()
        ->assertJsonStructure(['data' => ['status', 'providers']])
        ->assertJsonMissing(['attempts'])
        ->assertJsonPath('data.providers.0.safe_error', 'Moodle rejected test@example.com');
});

it('requires the diagnostics permission for advanced history', function (): void {
    $enrollment = Enrollment::factory()->create();
    ProvisioningAttempt::create([
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE,
        'trigger'       => ProvisioningTriggerEnum::PAYMENT,
        'status'        => ProvisioningAttemptStatusEnum::FAILED,
        'sequence'      => 1,
    ]);
    $this->authorized_user([PermissionEnum::ENROLLMENT_VIEW->value]);

    $this->getJson(route('api.v1.admin.enrollments.provisioning.diagnostics.advanced', $enrollment))
        ->assertForbidden();
});

it('returns secret-redacted advanced history to diagnostic viewers', function (): void {
    $enrollment = Enrollment::factory()->create();
    ProvisioningAttempt::create([
        'enrollment_id'    => $enrollment->id,
        'provider'         => ProvisioningProviderEnum::MOODLE,
        'trigger'          => ProvisioningTriggerEnum::PAYMENT,
        'status'           => ProvisioningAttemptStatusEnum::FAILED,
        'sequence'         => 1,
        'retryable'        => true,
        'failure_message'  => 'Moodle rejected test@example.com',
        'failure_metadata' => ['http_status' => 422, 'secret' => 'hidden', 'provider_trace_id' => 'trace-9'],
    ]);
    $this->authorized_user([PermissionEnum::ENROLLMENT_DIAGNOSTICS_VIEW->value]);

    $this->getJson(route('api.v1.admin.enrollments.provisioning.diagnostics.advanced', $enrollment))
        ->assertOk()
        ->assertJsonStructure(['data' => ['summary' => ['status', 'providers'], 'attempts' => [['provider', 'status', 'retryable', 'sequence', 'trigger', 'failure_code', 'classification', 'safe_error', 'correlation_id', 'context', 'created_at']]]])
        ->assertJsonPath('data.attempts.0.classification', 'recoverable')
        ->assertJsonPath('data.attempts.0.context.http_status', 422)
        ->assertJsonPath('data.attempts.0.safe_error', 'Moodle rejected test@example.com')
        ->assertJsonPath('data.attempts.0.context.secret', '[REDACTED]')
        ->assertJsonPath('data.attempts.0.context.provider_trace_id', 'trace-9');
});
