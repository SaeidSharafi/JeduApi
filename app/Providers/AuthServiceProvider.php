<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Policies\Admin\CategoryPolicy;
use App\Policies\Admin\CoursePolicy;
use App\Policies\Admin\DigitalAssetPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(DigitalAsset::class, DigitalAssetPolicy::class);

    }
}
