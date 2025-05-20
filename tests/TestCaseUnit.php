<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCaseUnit extends BaseTestCase
{


    protected function setUp(): void
    {
        parent::setUp();
        $this->app->singleton(function ($app): \App\Contracts\OtpGeneratorInterface {
            return new \Tests\Fakes\FakeOtpGenerator();
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
