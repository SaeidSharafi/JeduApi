<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Integrations\ImsClientContract;
use App\Contracts\Integrations\MoodleClientContract;
use App\Services\Fakes\FakeBbbService;
use App\Services\Fakes\FakeImsService;
use App\Services\Fakes\FakeMoodleService;
use App\Services\Fakes\FakeSpotPlayerService;
use App\Services\Integrations\BbbService;
use App\Services\Integrations\SpotPlayerService;
use App\Services\SettingsService;
use Illuminate\Support\ServiceProvider;

/**
 * @codeCoverageIgnore
 */
final class DemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SpotPlayerService::class,
            fn ($app): FakeSpotPlayerService => new FakeSpotPlayerService($app->make(SettingsService::class))
        );

        $this->app->singleton(
            BbbService::class,
            fn ($app): FakeBbbService => new FakeBbbService($app->make(SettingsService::class))
        );

        $this->app->singleton(
            MoodleClientContract::class,
            fn ($app): FakeMoodleService => new FakeMoodleService($app->make(SettingsService::class))
        );

        $this->app->singleton(
            ImsClientContract::class,
            fn (): FakeImsService => new FakeImsService()
        );
    }
}
