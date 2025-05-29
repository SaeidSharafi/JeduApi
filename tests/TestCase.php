<?php

namespace Tests;

use App\Contracts\OtpGeneratorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    //use CreatesApplication;
    use RefreshDatabase;
    use DateUtilTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->singleton(function ($app): \App\Contracts\OtpGeneratorInterface {
            return new \Tests\Fakes\FakeOtpGenerator();
        });
        $this->artisan('permissions:sync --guard=admin');

    }

    /**
     * Rolls back migrations
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
