<?php

declare(strict_types=1);

use App\Actions\Testing\ResetE2eEnvironmentAction;
use App\Exceptions\Testing\E2eResetFailedException;
use App\Http\Controllers\Testing\TestingDatabaseResetController;
use Illuminate\Http\Request;

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
    config(['e2e.control_key' => null]);
});

it('rejects reset requests outside E2E without invoking the reset action', function (): void {
    $action = $this->mock(ResetE2eEnvironmentAction::class);
    $action->shouldNotReceive('handle');

    $response = (new TestingDatabaseResetController())->reset(
        Request::create('/api/v1/e2e/reset', 'POST', server: ['HTTP_X_E2E_KEY' => 'secret']),
        $action,
    )->toResponse(Request::create('/api/v1/e2e/reset', 'POST'));

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true))->toHaveKeys(['message', 'errors', 'metadata']);
});

it('rejects reset requests with a missing or invalid control key', function (): void {
    app()->detectEnvironment(fn (): string => 'e2e');
    config(['e2e.control_key' => 'expected-secret']);

    $action = $this->mock(ResetE2eEnvironmentAction::class);
    $action->shouldNotReceive('handle');

    $response = (new TestingDatabaseResetController())->reset(
        Request::create('/api/v1/e2e/reset', 'POST', server: ['HTTP_X_E2E_KEY' => 'wrong-secret']),
        $action,
    )->toResponse(Request::create('/api/v1/e2e/reset', 'POST'));

    expect($response->getStatusCode())->toBe(403);
});

it('returns a standard success envelope for a completed reset', function (): void {
    app()->detectEnvironment(fn (): string => 'e2e');
    config(['e2e.control_key' => 'expected-secret']);

    $action = $this->mock(ResetE2eEnvironmentAction::class);
    $action->shouldReceive('handle')->once()->andReturn([
        'reset_id'  => 'reset-1',
        'readiness' => 'ready',
        'staff'     => ['token' => 'staff-token'],
        'customer'  => ['token' => 'customer-token'],
    ]);

    $request  = Request::create('/api/v1/e2e/reset', 'POST', server: ['HTTP_X_E2E_KEY' => 'expected-secret']);
    $response = (new TestingDatabaseResetController())->reset($request, $action)->toResponse($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['data'])->toMatchArray([
            'reset_id'  => 'reset-1',
            'readiness' => 'ready',
        ]);
});

it('returns conflict when another reset owns the distributed lock', function (): void {
    app()->detectEnvironment(fn (): string => 'e2e');
    config(['e2e.control_key' => 'expected-secret']);

    $action = $this->mock(ResetE2eEnvironmentAction::class);
    $action->shouldReceive('handle')->once()->andReturnNull();

    $request  = Request::create('/api/v1/e2e/reset', 'POST', server: ['HTTP_X_E2E_KEY' => 'expected-secret']);
    $response = (new TestingDatabaseResetController())->reset($request, $action)->toResponse($request);

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true))->toHaveKeys(['message', 'errors', 'metadata']);
});

it('returns a stable correlated error when reset infrastructure fails', function (): void {
    app()->detectEnvironment(fn (): string => 'e2e');
    config(['e2e.control_key' => 'expected-secret']);

    $action = $this->mock(ResetE2eEnvironmentAction::class);
    $action->shouldReceive('handle')->once()->andThrow(
        new E2eResetFailedException('reset-1', 'internal failure'),
    );

    $request  = Request::create('/api/v1/e2e/reset', 'POST', server: ['HTTP_X_E2E_KEY' => 'expected-secret']);
    $response = (new TestingDatabaseResetController())->reset($request, $action)->toResponse($request);

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true))->toMatchArray([
            'metadata' => [
                'error_code' => 'E2E_RESET_FAILED',
                'reset_id'   => 'reset-1',
            ],
        ]);
});
