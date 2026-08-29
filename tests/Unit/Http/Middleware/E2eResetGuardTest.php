<?php

declare(strict_types=1);

use App\Http\Middleware\E2eResetGuard;
use App\Services\Testing\E2eResetState;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
});

it('allows requests when the application is not running in E2E', function (): void {
    $state = $this->mock(E2eResetState::class);
    $state->shouldNotReceive('isResetting');

    $response = (new E2eResetGuard())->handle(
        Request::create('/api/v1/shop/products', 'GET'),
        fn (): Response => response('ok'),
    );

    expect($response->getStatusCode())->toBe(200);
});

it('blocks application requests while an E2E reset is active', function (): void {
    app()->detectEnvironment(fn (): string => 'e2e');

    $state = $this->mock(E2eResetState::class);
    $state->shouldReceive('isResetting')->once()->andReturnTrue();

    $response = (new E2eResetGuard())->handle(
        Request::create('/api/v1/shop/products', 'GET'),
        fn (): Response => response('should not run'),
    );

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getContent())->toContain('E2E reset is in progress.');
});

it('allows the reset endpoint through while a reset is active', function (): void {
    app()->detectEnvironment(fn (): string => 'e2e');

    $state = $this->mock(E2eResetState::class);
    $state->shouldNotReceive('isResetting');

    $response = (new E2eResetGuard())->handle(
        Request::create('/api/v1/e2e/reset', 'POST'),
        fn (): Response => response('ok'),
    );

    expect($response->getStatusCode())->toBe(200);
});
