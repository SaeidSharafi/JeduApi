<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;

arch('the shared test case resets the database lazily')
    ->expect(Tests\TestCase::class)
    ->toUse(LazilyRefreshDatabase::class);

arch('individual tests do not reset the database themselves')
    ->expect('Tests')
    ->not->toUse(RefreshDatabase::class);
