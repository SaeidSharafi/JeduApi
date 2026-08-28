<?php

declare(strict_types=1);

use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use App\Services\Provisioning\ProvisioningDiagnosticsService;

it('returns safe provider diagnostics without raw payloads', function (): void {
    $enrollment = Enrollment::factory()->create();
    $enrollment->update([
        'provisioning_plan'   => ['version' => 1, 'providers' => [['provider' => 'moodle', 'applicable' => true, 'readiness' => 'ready']], 'status' => 'degraded'],
        'provisioning_status' => 'degraded',
        'provisioning_data'   => ['providers' => ['moodle' => ['status' => 'failed', 'last_error' => 'safe failure', 'data' => ['moodle_user_id' => 4, 'token' => 'secret']]]],
    ]);

    $diagnostics = app(ProvisioningDiagnosticsService::class)->diagnostics($enrollment);

    expect($diagnostics->status)->toBe('degraded')
        ->and($diagnostics->providers[0]->safe_error)->toBe('Provider failure details were redacted.')
        ->and($diagnostics->providers[0]->references)->toBe(['moodle_user_id' => 4]);
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
