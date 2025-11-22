<?php

namespace Tests;

use App\Contracts\OtpGeneratorInterface;
use App\Http\Middleware\AdminAuditMiddleware;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\Traits\DateUtilTestTrait;

abstract class TestCase extends BaseTestCase
{
    //use CreatesApplication;
    use RefreshDatabase;
    use DateUtilTestTrait;

    protected $seed = true;
    protected $seeder = PermissionsSeeder::class;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AdminAuditMiddleware::class);
        $this->app->singleton(function ($app): OtpGeneratorInterface {
            return new \Tests\Support\Fakes\FakeOtpGenerator();
        });

    }

    /**
     * Rolls back migrations
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
