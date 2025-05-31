<?php

namespace App\Providers;

use App\Faker\PersianFakesProvider;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\ServiceProvider;

class PersianFakesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Generator::class, function () {
            $faker = Factory::create();
            $faker->addProvider(new PersianFakesProvider($faker));
            return $faker;
        });
    }
}
