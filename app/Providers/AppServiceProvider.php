<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\OtpGeneratorInterface;
use App\Models\Admin;
use App\Models\User;
use App\Services\DefaultOtpGenerator;
use Illuminate\Database\Eloquent\Model;
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
        Model::preventLazyLoading(!app()->isProduction());
        Gate::before(function (Admin|User $user, mixed $ability) {
            return ($user instanceof Admin && $user->is_admin) ? true : null;
        });
    }
}
