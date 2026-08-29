<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Integrations\BbbClientContract;
use App\Contracts\Integrations\ImsClientContract;
use App\Contracts\Integrations\MoodleClientContract;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Contracts\Integrations\SpotPlayerClientContract;
use App\Enums\ProvisioningProviderEnum;
use App\Services\Fakes\FakeBbbService;
use App\Services\Fakes\FakeImsService;
use App\Services\Fakes\FakeMoodleService;
use App\Services\Fakes\FakeSkyroomService;
use App\Services\Fakes\FakeSpotPlayerService;
use App\Services\SettingsService;
use App\Services\Testing\E2eResetState;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use LogicException;

/**
 * @codeCoverageIgnore
 */
final class E2eServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SpotPlayerClientContract::class, FakeSpotPlayerService::class);
        $this->app->singleton(SkyroomClientContract::class, FakeSkyroomService::class);

        $this->app->singleton(BbbClientContract::class, fn (): FakeBbbService => new FakeBbbService());
        $this->app->singleton(
            MoodleClientContract::class,
            fn ($app): FakeMoodleService => new FakeMoodleService($app->make(SettingsService::class)),
        );
        $this->app->singleton(ImsClientContract::class, fn (): FakeImsService => new FakeImsService());

        Queue::looping(function (Looping $event): bool {
            $state = app(E2eResetState::class);

            if ($state->isResetting()) {
                return false;
            }

            $state->markWorkerReady(
                sprintf('%s:%s:%s', gethostname(), getmypid(), $event->queue),
                30,
            );

            return true;
        });
        Queue::before(function (JobProcessing $event): void {
            app(E2eResetState::class)->markJobStarted($event->job->getJobId(), 900);
        });
        Queue::after(function (JobProcessed $event): void {
            app(E2eResetState::class)->markJobFinished($event->job->getJobId());
        });
        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            app(E2eResetState::class)->markJobFinished($event->job->getJobId());
        });

        $this->assertCompleteProviderCoverage();
    }

    private function assertCompleteProviderCoverage(): void
    {
        foreach (ProvisioningProviderEnum::cases() as $provider) {
            $client    = $this->app->make($this->clientContractFor($provider));
            $fakeClass = $this->fakeClientFor($provider);

            if (! $client instanceof $fakeClass) {
                throw new LogicException("E2E provider [{$provider->value}] is not backed by its simulated client.");
            }
        }
    }

    /** @return class-string */
    private function clientContractFor(ProvisioningProviderEnum $provider): string
    {
        return match ($provider) {
            ProvisioningProviderEnum::IMS => ImsClientContract::class,
            ProvisioningProviderEnum::MOODLE,
            ProvisioningProviderEnum::MOODLE_QUIZ => MoodleClientContract::class,
            ProvisioningProviderEnum::SPOTPLAYER  => SpotPlayerClientContract::class,
            ProvisioningProviderEnum::BBB         => BbbClientContract::class,
            ProvisioningProviderEnum::SKYROOM     => SkyroomClientContract::class,
        };
    }

    /** @return class-string */
    private function fakeClientFor(ProvisioningProviderEnum $provider): string
    {
        return match ($provider) {
            ProvisioningProviderEnum::IMS => FakeImsService::class,
            ProvisioningProviderEnum::MOODLE,
            ProvisioningProviderEnum::MOODLE_QUIZ => FakeMoodleService::class,
            ProvisioningProviderEnum::SPOTPLAYER  => FakeSpotPlayerService::class,
            ProvisioningProviderEnum::BBB         => FakeBbbService::class,
            ProvisioningProviderEnum::SKYROOM     => FakeSkyroomService::class,
        };
    }
}
