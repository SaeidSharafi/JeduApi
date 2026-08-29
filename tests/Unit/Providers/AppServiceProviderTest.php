<?php

declare(strict_types=1);

use App\Contracts\Integrations\BbbClientContract;
use App\Contracts\Integrations\ImsClientContract;
use App\Contracts\Integrations\MoodleClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Contracts\Integrations\SpotPlayerClientContract;
use App\Enums\ProvisioningProviderEnum;
use App\Providers\AppServiceProvider;
use App\Services\Fakes\FakeBbbService;
use App\Services\Fakes\FakeImsService;
use App\Services\Fakes\FakeMoodleService;
use App\Services\Fakes\FakeSkyroomService;
use App\Services\Fakes\FakeSpotPlayerService;
use App\Services\Integrations\BbbService;
use App\Services\Integrations\ImsService;
use App\Services\Integrations\MoodleService;
use App\Services\Integrations\SkyroomService;
use App\Services\Integrations\SpotPlayerService;

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
    config([
        'e2e.control_key'            => null,
        'payments.simulator.enabled' => false,
    ]);
});

it('rejects every E2E-only control when production boots', function (string $configKey, mixed $value): void {
    app()->detectEnvironment(fn (): string => 'production');
    config([$configKey => $value]);

    expect(fn (): AppServiceProvider => tap(new AppServiceProvider(app()))->register())
        ->toThrow(LogicException::class, 'E2E-only configuration is not allowed in production');
})->with([
    'control secret'    => ['e2e.control_key', 'secret'],
    'payment simulator' => ['payments.simulator.enabled', true],
]);

it('allows production boot when E2E controls use their safe defaults', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn (): AppServiceProvider => tap(new AppServiceProvider(app()))->register())
        ->not->toThrow(Throwable::class);
});

it('resolves every provider to its real client outside E2E', function (): void {
    expect(app(ImsClientContract::class))->toBeInstanceOf(ImsService::class)
        ->and(app(MoodleClientContract::class))->toBeInstanceOf(MoodleService::class)
        ->and(app(SpotPlayerClientContract::class))->toBeInstanceOf(SpotPlayerService::class)
        ->and(app(BbbClientContract::class))->toBeInstanceOf(BbbService::class)
        ->and(app(SkyroomClientContract::class))->toBeInstanceOf(SkyroomService::class);
});

it('registers a simulated client for every provisioning provider in E2E', function (): void {
    app()->detectEnvironment(fn (): string => 'e2e');

    (new AppServiceProvider(app()))->register();

    expect(app(ImsClientContract::class))->toBeInstanceOf(FakeImsService::class)
        ->and(app(MoodleClientContract::class))->toBeInstanceOf(FakeMoodleService::class)
        ->and(app(SpotPlayerClientContract::class))->toBeInstanceOf(FakeSpotPlayerService::class)
        ->and(app(BbbClientContract::class))->toBeInstanceOf(FakeBbbService::class)
        ->and(app(SkyroomClientContract::class))->toBeInstanceOf(FakeSkyroomService::class)
        ->and(ProvisioningProviderEnum::cases())->toHaveCount(6);
});
