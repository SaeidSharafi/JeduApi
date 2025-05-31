<?php

declare(strict_types=1);

namespace App\Providers;

use App\Faker\PersianFakesProvider;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\ServiceProvider;

final class PersianFakesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Generator::class, function () {
            $faker = Factory::create(config('app.locale'));
            $faker->addProvider(new PersianFakesProvider($faker));

            return $faker;
        });
    }
}
