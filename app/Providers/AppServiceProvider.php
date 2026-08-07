<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CartIdentifier;
use App\Contracts\OtpGeneratorInterface;
use App\Enums\System\MorphTypeEnum;
use App\Services\Cart\RequestCartIdentifier;
use App\Services\DefaultOtpGenerator;
use App\Services\Discounts\DiscountHandlerRegistry;
use App\Services\Discounts\DiscountMetadataService;
use App\Services\RequestDataCacheService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Image;
use Plank\Mediable\Facades\ImageManipulator;
use Plank\Mediable\ImageManipulation;
use Plank\Mediable\Media;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        if (! $this->app->environment('production') && config('app.use_fake_providers')) {
            $this->app->register(DemoServiceProvider::class);
        }

        $this->app->singleton(OtpGeneratorInterface::class, DefaultOtpGenerator::class);
        $this->app->singleton(DiscountHandlerRegistry::class);
        $this->app->singleton(DiscountMetadataService::class);
        $this->app->singleton(\App\Services\CacheInvalidationService::class);
        $this->app->singleton(function ($app): RequestDataCacheService {
            return new RequestDataCacheService();
        });

        // Scoped: singleton per request, but fresh for each request
        // This ensures auth state is checked dynamically but token minting is stable within a request
        $this->app->scoped(CartIdentifier::class, RequestCartIdentifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        include_once __DIR__.'/../Helpers/helpers.php';

        RateLimiter::for('otp-initiate', function (Request $request): array {
            $maxAttempts  = (int) config('otp.rate_limiting.initiate.max_attempts', 30);
            $decayMinutes = (int) config('otp.rate_limiting.initiate.decay_minutes', 1);
            $identifier   = mb_strtolower(mb_trim((string) $request->input('identifier')));

            return [
                Limit::perMinute($maxAttempts, $decayMinutes)
                    ->by(sprintf('otp-initiate:ip:%s', $request->ip())),
                Limit::perMinute($maxAttempts, $decayMinutes)
                    ->by(sprintf('otp-initiate:id:%s:%s', $request->ip(), $identifier)),
            ];
        });

        RateLimiter::for('otp-resend', function (Request $request): array {
            $maxAttempts  = (int) config('otp.rate_limiting.resend.max_attempts', 20);
            $decayMinutes = (int) config('otp.rate_limiting.resend.decay_minutes', 1);
            $identifier   = mb_strtolower(mb_trim((string) $request->input('identifier')));

            return [
                Limit::perMinute($maxAttempts, $decayMinutes)
                    ->by(sprintf('otp-resend:ip:%s', $request->ip())),
                Limit::perMinute($maxAttempts, $decayMinutes)
                    ->by(sprintf('otp-resend:id:%s:%s', $request->ip(), $identifier)),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request): array {
            $maxAttempts  = (int) config('otp.rate_limiting.verify.max_attempts', 60);
            $decayMinutes = (int) config('otp.rate_limiting.verify.decay_minutes', 1);
            $identifier   = mb_strtolower(mb_trim((string) $request->input('identifier')));

            return [
                Limit::perMinute($maxAttempts, $decayMinutes)
                    ->by(sprintf('otp-verify:ip:%s', $request->ip())),
                Limit::perMinute($maxAttempts, $decayMinutes)
                    ->by(sprintf('otp-verify:id:%s:%s', $request->ip(), $identifier)),
            ];
        });

        Model::preventLazyLoading(! app()->isProduction());
        Relation::enforceMorphMap(MorphTypeEnum::forMorphMap());
        // @codeCoverageIgnoreStart
        ImageManipulator::defineVariant(
            'thumb',
            ImageManipulation::make(function (
                Image $image,
                Media $originalMedia
            ): void {
                $image->cover(
                    config('mediable.image_variants.thumb.width'),
                    config('mediable.image_variants.thumb.height')
                );
            })
                ->toDestination(config('mediable.default_disk', 'public'), 'thumb')
                ->optimize()->outputWebpFormat(),
        );
        // @codeCoverageIgnoreEnd
    }
}
