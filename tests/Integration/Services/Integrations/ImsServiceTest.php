<?php

declare(strict_types=1);

use App\Exceptions\Integrations\ExternalProvisioningException;
use App\Services\Integrations\ImsService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    config([
        'services.ims.base_url'             => 'https://ims.test',
        'services.ims.enrollments_endpoint' => '/api/v1/enrol',
        'services.ims.api_key'              => 'ims-key',
        'services.ims.api_key_header'       => 'X-API-KEY',
        'services.ims.timeout'              => 15,
    ]);
});

it('provisions enrollment when ims returns successful response', function (): void {
    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => true,
            'message' => 'ok',
            'data'    => ['enrollment_id' => 1001],
        ], 200),
    ]);

    $service = app(ImsService::class);

    $payload = [
        'student'       => ['external_user_id' => 'u-1'],
        'registrations' => [['course_code' => 'IMS-1']],
    ];

    $response = $service->provisionEnrollment($payload);

    expect($response['status'])->toBeTrue()
        ->and($response['message'])->toBe('ok')
        ->and(data_get($response, 'data.enrollment_id'))->toBe(1001);

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://ims.test/api/v1/enrol'
            && $request->hasHeader('X-API-KEY', 'ims-key')
            && $request->data() === $payload;
    });
});

it('throws when ims request fails with non success status', function (): void {
    Http::fake([
        'https://ims.test/*' => Http::response([], 500),
    ]);

    $service = app(ImsService::class);

    expect(fn () => $service->provisionEnrollment(['registrations' => []]))
        ->toThrow(ExternalProvisioningException::class, 'IMS provisioning request failed.');
});

it('throws ims business errors from response errors array', function (): void {
    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => false,
            'message' => 'not-ok',
            'errors'  => ['invalid national code', 'course full'],
        ], 200),
    ]);

    $service = app(ImsService::class);

    expect(fn () => $service->provisionEnrollment(['registrations' => []]))
        ->toThrow(ExternalProvisioningException::class, 'invalid national code; course full');
});

it('uses default ims error message when errors array empty', function (): void {
    Http::fake([
        'https://ims.test/*' => Http::response([
            'status'  => false,
            'message' => 'not-ok',
            'errors'  => [],
        ], 200),
    ]);

    $service = app(ImsService::class);

    expect(fn () => $service->provisionEnrollment(['registrations' => []]))
        ->toThrow(ExternalProvisioningException::class, 'IMS provisioning response was not successful.');
});
