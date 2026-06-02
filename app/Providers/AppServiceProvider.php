<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CartIdentifier;
use App\Contracts\OtpGeneratorInterface;
use App\Providers\DemoServiceProvider;
use App\Enums\System\MorphTypeEnum;
use App\Services\Cart\RequestCartIdentifier;
use App\Services\DefaultOtpGenerator;
use App\Services\Discounts\DiscountHandlerRegistry;
use App\Services\Discounts\DiscountMetadataService;
use App\Services\RequestDataCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        if (!$this->app->environment('production') && config('app.use_fake_providers')) {
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
