<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Services\Fakes\FakeMoodleService;
use App\Services\Integrations\MoodleService;

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
    config([
        'e2e.control_key'            => null,
        'app.use_fake_providers'     => false,
        'payments.simulator.enabled' => false,
    ]);
});

it('rejects every E2E-only control when production boots', function (string $configKey, mixed $value): void {
    app()->detectEnvironment(fn (): string => 'production');
    config([$configKey => $value]);

    expect(fn (): AppServiceProvider => tap(new AppServiceProvider(app()))->register())
        ->toThrow(LogicException::class, 'E2E-only configuration is not allowed in production');
})->with([
    'control secret'      => ['e2e.control_key', 'secret'],
    'simulated providers' => ['app.use_fake_providers', true],
    'payment simulator'   => ['payments.simulator.enabled', true],
]);

it('allows production boot when E2E controls use their safe defaults', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn (): AppServiceProvider => tap(new AppServiceProvider(app()))->register())
        ->not->toThrow(Throwable::class);
});

it('does not register simulated providers outside the E2E environment', function (): void {
    config(['app.use_fake_providers' => true]);

    expect(app()->bound(MoodleService::class))->toBeFalse();
});

it('registers simulated providers only in the E2E environment', function (): void {
    app()->detectEnvironment(fn (): string => 'e2e');

    (new AppServiceProvider(app()))->register();

    expect(app(MoodleService::class))->toBeInstanceOf(FakeMoodleService::class);
});
