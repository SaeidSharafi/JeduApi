<?php

declare(strict_types=1);

use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Helpers\ProvisioningErrorContext;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

mutates(ProvisioningErrorContext::class);

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

describe('Provisioning API error disclosure', function (): void {
    it('hides all upstream context from API errors when debug is disabled', function (): void {
        config(['app.debug' => false]);
        $failure = new RecoverableProvisioningException('Provisioning failed', metaData: [
            'http_status'  => 502,
            'wstoken'      => 'upstream-token-secret',
            'password'     => 'student-password',
            'license_key'  => 'license-secret',
            'raw_response' => ['message' => 'student@example.test used phone 09121234567 and id 1234567890'],
        ]);

        $response = $failure->render(Request::create('/api/v1/provisioning', 'GET', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]));

        expect($response)->not->toBeNull();
        TestResponse::fromBaseResponse($response)
            ->assertStatus(503)
            ->assertJsonPath('message', 'Provisioning failed')
            ->assertJsonPath('errors', null)
            ->assertJsonPath('metadata', [])
            ->assertDontSee('upstream-token-secret')
            ->assertDontSee('student-password')
            ->assertDontSee('license-secret')
            ->assertDontSee('student@example.test');
    });

    it('returns scrubbed safe context in API errors when debug is enabled', function (): void {
        config(['app.debug' => true]);
        $failure = new RecoverableProvisioningException('Provisioning failed', metaData: [
            'http_status'  => 502,
            'endpoint'     => '/webservice/rest/server.php',
            'wstoken'      => 'upstream-token-secret',
            'password'     => 'student-password',
            'license_key'  => 'license-secret',
            'raw_response' => ['message' => 'student@example.test used phone 09121234567 and id 1234567890'],
        ]);

        $response = $failure->render(Request::create('/api/v1/provisioning', 'GET', server: [
            'HTTP_ACCEPT' => 'application/json',
        ]));

        expect($response)->not->toBeNull();
        TestResponse::fromBaseResponse($response)
            ->assertStatus(503)
            ->assertJsonPath('errors.debug.http_status', 502)
            ->assertJsonPath('errors.debug.endpoint', '/webservice/rest/server.php')
            ->assertJsonPath('errors.debug.wstoken', '[REDACTED]')
            ->assertJsonPath('errors.debug.password', '[REDACTED]')
            ->assertJsonPath('errors.debug.license_key', '[REDACTED]')
            ->assertJsonPath('errors.debug.raw_response', '{"message":"[REDACTED] used phone [REDACTED] and id [REDACTED]"}')
            ->assertDontSee('upstream-token-secret')
            ->assertDontSee('student-password')
            ->assertDontSee('license-secret')
            ->assertDontSee('student@example.test')
            ->assertDontSee('09121234567')
            ->assertDontSee('1234567890');
    });

    it('falls through to the normal HTML error handler for non-API web requests', function (): void {
        $failure = new RecoverableProvisioningException('Provisioning failed');

        expect($failure->render(Request::create('/dashboard', 'GET')))->toBeNull();
    });
});
