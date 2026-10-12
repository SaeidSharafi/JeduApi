<?php

declare(strict_types=1);

use App\Enums\ProvisioningAttemptStatusEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningTriggerEnum;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\Enrollment;
use App\Models\ProvisioningAttempt;
use App\Services\Provisioning\ProvisioningAttemptService;

covers(ProvisioningAttemptService::class);

it('persists the full scrubbed provider context when an attempt fails', function (): void {
    $enrollment = Enrollment::factory()->create();
    $attempt    = ProvisioningAttempt::create([
        'enrollment_id' => $enrollment->id,
        'provider'      => ProvisioningProviderEnum::IMS,
        'trigger'       => ProvisioningTriggerEnum::PAYMENT,
        'status'        => ProvisioningAttemptStatusEnum::RUNNING,
        'sequence'      => 1,
        'retryable'     => false,
    ]);

    $exception = new UnrecoverableProvisioningException(
        'IMS rejected the student.',
        422,
        null,
        [
            'http_status'       => 422,
            'endpoint'          => '/api/v2/student',
            'function'          => 'storeStudent',
            'raw_body_snippet'  => '{"message":"email test@example.com is taken"}',
            'validation_errors' => ['gender' => ['The selected gender is invalid.']],
        ]
    );

    // Mirrors ProvisionEnrollmentProviderJob, which hands the exception's metaData
    // to fail() as its own argument.
    app(ProvisioningAttemptService::class)->fail($attempt, $exception, true, $exception->metaData);

    $metadata = $attempt->fresh()->failure_metadata;

    expect($metadata)->toMatchArray([
        'http_status' => 422,
        'endpoint'    => '/api/v2/student',
        'function'    => 'storeStudent',
    ])
        ->and($metadata['raw_body_snippet'])->toBe('{"message":"email [REDACTED] is taken"}')
        ->and($metadata['validation_errors'])->toBe(['gender' => 'The selected gender is invalid.']);
});
