<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\OtpGeneratorInterface;
use App\Enums\MorphTypeEnum;
use App\Models\Admin;
use App\Models\User;
use App\Services\DefaultOtpGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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

    }
}
