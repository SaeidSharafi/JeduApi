<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Fakes\FakeBbbService;
use App\Services\Fakes\FakeMoodleService;
use App\Services\Fakes\FakeSpotPlayerService;
use App\Services\Integrations\BbbService;
use App\Services\Integrations\MoodleService;
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
            fn ($app) => new FakeSpotPlayerService($app->make(SettingsService::class))
        );

        $this->app->singleton(
            BbbService::class,
            fn ($app) => new FakeBbbService($app->make(SettingsService::class))
        );

        $this->app->singleton(
            MoodleService::class,
            fn ($app) => new FakeMoodleService($app->make(SettingsService::class))
        );
    }
}
