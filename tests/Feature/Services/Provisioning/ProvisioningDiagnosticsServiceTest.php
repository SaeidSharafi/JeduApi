<?php

declare(strict_types=1);

use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use App\Services\Provisioning\ProvisioningDiagnosticsService;

covers(ProvisioningDiagnosticsService::class);

it('exposes the sanitized provider failure message', function (string $lastError, string $expected): void {
    $enrollment = Enrollment::factory()->create();
    $enrollment->update([
        'provisioning_plan'   => ['version' => 1, 'providers' => [['provider' => 'moodle', 'applicable' => true, 'readiness' => 'ready']], 'status' => 'degraded'],
        'provisioning_status' => 'degraded',
        'provisioning_data'   => ['providers' => ['moodle' => ['status' => 'failed', 'last_error' => $lastError, 'data' => ['moodle_user_id' => 4, 'token' => 'secret']]]],
    ]);

    $diagnostics = app(ProvisioningDiagnosticsService::class)->diagnostics($enrollment);

    expect($diagnostics->status)->toBe('degraded')
        ->and($diagnostics->providers[0]->safe_error)->toBe($expected)
        ->and($diagnostics->providers[0]->references)->toBe(['moodle_user_id' => 4]);
})->with([
    'authored message passes through' => [
        'IMS student outcome is ambiguous; manual verification required.',
        'IMS student outcome is ambiguous; manual verification required.',
    ],
    'upstream PII is scrubbed' => [
        'Moodle rejected test@example.com for 09123456789',
        'Moodle rejected [REDACTED] for [REDACTED]',
    ],
]);

it('falls back to the failure message when validation metadata is empty', function (): void {
    $enrollment = Enrollment::factory()->create([
        'provisioning_data' => ['providers' => ['ims' => ['status' => 'manual_action_required', 'last_error' => 'اعتبارسنجی اطلاعات در درگاه /api/v2/student ناموفق بود.']]],
    ]);
    $enrollment->update([
        'provisioning_plan'   => ['version' => 1, 'providers' => [['provider' => 'ims', 'applicable' => true, 'readiness' => 'ready']], 'status' => 'ready'],
        'provisioning_status' => 'manual_action_required',
    ]);
    ProvisioningAttempt::create([
        'enrollment_id'    => $enrollment->id,
        'provider'         => ProvisioningProviderEnum::IMS,
        'trigger'          => ProvisioningTriggerEnum::PAYMENT,
        'status'           => ProvisioningAttemptStatusEnum::MANUAL_ACTION_REQUIRED,
        'sequence'         => 1,
        'retryable'        => false,
        'failure_metadata' => ['http_status' => 422, 'endpoint' => '/api/v2/student', 'validation_errors' => []],
    ]);

    $diagnostics = app(ProvisioningDiagnosticsService::class)->diagnostics($enrollment);

    expect($diagnostics->providers[0]->safe_error)
        ->toBe('اعتبارسنجی اطلاعات در درگاه /api/v2/student ناموفق بود.');
});

it('shows sanitized provider validation details to diagnostic viewers', function (): void {
    $enrollment = Enrollment::factory()->create([
        'provisioning_data' => ['providers' => ['ims' => ['status' => 'failed', 'last_error' => 'validation failed']]],
    ]);
    $enrollment->update([
        'provisioning_plan'   => ['version' => 1, 'providers' => [['provider' => 'ims', 'applicable' => true, 'readiness' => 'ready']], 'status' => 'degraded'],
        'provisioning_status' => 'degraded',
    ]);
    ProvisioningAttempt::create([
        'enrollment_id'    => $enrollment->id,
        'provider'         => ProvisioningProviderEnum::IMS,
        'trigger'          => ProvisioningTriggerEnum::PAYMENT,
        'status'           => ProvisioningAttemptStatusEnum::FAILED,
        'sequence'         => 1,
        'retryable'        => false,
        'failure_metadata' => [
            'http_status'       => 422,
            'endpoint'          => '/api/v2/student',
            'validation_errors' => [
                'gender'   => ['The selected gender is invalid.'],
                'civil_id' => ['The civil ID 1234567890 is invalid.'],
                'email'    => ['The email test@example.com is invalid.'],
            ],
        ],
    ]);

    $diagnostics = app(ProvisioningDiagnosticsService::class)->diagnostics($enrollment);

    expect($diagnostics->providers[0]->safe_error)
        ->toContain('gender:')
        ->toContain('civil_id:')
        ->toContain('[REDACTED]')
        ->not->toContain('1234567890')
        ->not->toContain('test@example.com');
});

it('returns the sanitized failure message and full context per attempt', function (): void {
    $enrollment = Enrollment::factory()->create();
    ProvisioningAttempt::create([
        'enrollment_id'    => $enrollment->id,
        'provider'         => ProvisioningProviderEnum::IMS,
        'trigger'          => ProvisioningTriggerEnum::PAYMENT,
        'status'           => ProvisioningAttemptStatusEnum::FAILED,
        'sequence'         => 1,
        'retryable'        => false,
        'failure_message'  => 'IMS rejected test@example.com',
        'failure_metadata' => [
            'http_status'      => 422,
            'endpoint'         => '/api/v2/student?debug=1',
            'function'         => 'storeStudent',
            'raw_body_snippet' => 'civil_id 1234567890 is invalid',
        ],
    ]);

    $diagnostics = app(ProvisioningDiagnosticsService::class)->diagnostics($enrollment, true);

    expect($diagnostics->attempts[0]->safe_error)->toBe('IMS rejected [REDACTED]')
        ->and($diagnostics->attempts[0]->context)->toMatchArray([
            'http_status' => 422,
            'function'    => 'storeStudent',
            'endpoint'    => '/api/v2/student',
        ])
        ->and($diagnostics->attempts[0]->context['raw_body_snippet'])->toBe('civil_id [REDACTED] is invalid');
});

it('returns newest attempt history first and preserves terminal retryability', function (): void {
    $enrollment = Enrollment::factory()->create();
    ProvisioningAttempt::create([
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE,
        'trigger'       => ProvisioningTriggerEnum::PAYMENT,
        'status'        => ProvisioningAttemptStatusEnum::FAILED,
        'sequence'      => 1,
        'retryable'     => false,
    ]);
    ProvisioningAttempt::create([
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::MOODLE,
        'trigger'       => ProvisioningTriggerEnum::RETRY,
        'status'        => ProvisioningAttemptStatusEnum::FAILED,
        'sequence'      => 2,
        'retryable'     => true,
    ]);
    ProvisioningAttempt::create([
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::SPOTPLAYER,
        'trigger'       => ProvisioningTriggerEnum::PAYMENT,
        'status'        => ProvisioningAttemptStatusEnum::SUCCEEDED,
        'sequence'      => 1,
        'retryable'     => false,
    ]);

    $diagnostics = app(ProvisioningDiagnosticsService::class)->diagnostics($enrollment, true);

    expect($diagnostics->attempts[0]->provider)->toBe('spotplayer')
        ->and($diagnostics->attempts[1]->sequence)->toBe(2)
        ->and($diagnostics->attempts[2]->sequence)->toBe(1)
        ->and($diagnostics->attempts[0]->created_at)->not->toBeNull();
});

it('returns empty typed collections when no providers or attempts exist', function (): void {
    $enrollment = Enrollment::factory()->create();
    $enrollment->update(['provisioning_plan' => ['version' => 1, 'providers' => []], 'provisioning_status' => 'healthy']);
    $diagnostics = app(ProvisioningDiagnosticsService::class)->diagnostics($enrollment->fresh(), true);

    expect($diagnostics->summary->providers)->toHaveCount(0)
        ->and($diagnostics->attempts)->toHaveCount(0);
});
