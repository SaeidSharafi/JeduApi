<?php

namespace App\Providers;

use App\Models\Course;
use App\Policies\Admin\CoursePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        Gate::policy(Course::class, CoursePolicy::class);
    }
}
