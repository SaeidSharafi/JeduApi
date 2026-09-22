<?php

declare(strict_types=1);

use App\Helpers\ProvisioningErrorContext;

covers(ProvisioningErrorContext::class);

it('redacts PII patterns and truncates an upstream body', function (): void {
    $body = 'user me@example.com phone 09121234567 id 1234567890';

    $sanitized = ProvisioningErrorContext::sanitizeBody($body);

    expect($sanitized)
        ->not->toContain('me@example.com')
        ->not->toContain('09121234567')
        ->not->toContain('1234567890')
        ->toContain('[REDACTED]');
});

it('replaces sensitive keys instead of logging their values', function (): void {
    $sanitized = ProvisioningErrorContext::sanitize([
        'errorcode'   => 'invalidparameter',
        'license_key' => 'SPOT-LICENCE-SECRET',
        'player_url'  => 'https://player.spot.test/secret-token',
        'wstoken'     => 'moodle-token',
    ]);

    expect($sanitized)->toMatchArray([
        'errorcode'   => 'invalidparameter',
        'license_key' => '[REDACTED]',
        'player_url'  => '[REDACTED]',
        'wstoken'     => '[REDACTED]',
    ]);
});

it('scrubs body-shaped keys whether they hold a string or an array', function (): void {
    $sanitized = ProvisioningErrorContext::sanitize([
        'raw_body_snippet' => 'written to admin@example.com',
        'raw_response'     => ['message' => 'reached 09129999999'],
        'debuginfo'        => 'failed for student 1234567890',
    ]);

    expect($sanitized['raw_body_snippet'])->not->toContain('admin@example.com')
        ->and($sanitized['raw_response'])->not->toContain('09129999999')
        ->and($sanitized['debuginfo'])->not->toContain('1234567890');
});

it('leaves non-sensitive scalar context untouched and skips empty metadata', function (): void {
    expect(ProvisioningErrorContext::sanitize([
        'http_status' => 500,
        'endpoint'    => '/webservice/rest/server.php',
        'function'    => 'enrol_manual_enrol_users',
    ]))->toBe([
        'http_status' => 500,
        'endpoint'    => '/webservice/rest/server.php',
        'function'    => 'enrol_manual_enrol_users',
    ])->and(ProvisioningErrorContext::sanitize([]))->toBe([]);
});
