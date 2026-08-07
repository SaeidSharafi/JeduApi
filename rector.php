<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap',
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/app/Data',
        __DIR__.'/app/Scribe',
    ])
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_120,
        LaravelSetList::LARAVEL_COLLECTION,
    ])
    ->withPHPStanConfigs([
        __DIR__.'/phpstan-for-rector.neon',
    ])
    ->withPreparedSets(typeDeclarations: true)
    ->withDeadCodeLevel(10)
    ->withCodeQualityLevel(10);
