<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\OtpGeneratorInterface;
use App\Enums\MorphTypeEnum;
use App\Services\DefaultOtpGenerator;
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
        $this->app->singleton(OtpGeneratorInterface::class, DefaultOtpGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        include_once __DIR__.'/../Helpers/helpers.php';

        Model::preventLazyLoading(! app()->isProduction());
        Relation::enforceMorphMap(MorphTypeEnum::forMorphMap());
        ImageManipulator::defineVariant(
            'thumb',
            ImageManipulation::make(function (
                Image $image,
                Media $originalMedia
            ) {
                $image->cover(
                    config('mediable.image_variants.thumb.width'),
                    config('mediable.image_variants.thumb.height')
                );
            })
                ->toDestination(config('mediable.default_disk', 'public'), 'thumb')
                ->optimize()->outputWebpFormat(),
        );
    }
}
