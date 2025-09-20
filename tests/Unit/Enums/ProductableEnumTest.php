<?php

declare(strict_types=1);

use App\Enums\ProductableEnum;

it('return alias correctly', function (): void {
    $alias = ProductableEnum::getAlias(App\Models\Course::class);
    expect($alias)->toBe('course');
    $alias = ProductableEnum::getAlias(App\Models\DigitalAsset::class);
    expect($alias)->toBe('digital_asset');
    $alias = ProductableEnum::getAlias(App\Models\Seminar::class);
    expect($alias)->toBe('seminar');
    $alias = ProductableEnum::getAlias('non_existent_class');
    expect($alias)->toBeNull();
});

it('return model class correctly', function (): void {
    $modelClass = ProductableEnum::COURSE->getModelClass();
    expect($modelClass)->toBe(App\Models\Course::class);
    $modelClass = ProductableEnum::SEMINAR->getModelClass();
    expect($modelClass)->toBe(App\Models\Seminar::class);
    $modelClass = ProductableEnum::DIGITAL_ASSET->getModelClass();
    expect($modelClass)->toBe(App\Models\DigitalAsset::class);
});

it('return table from type correctly', function (): void {
    $table = ProductableEnum::getTableFromType('course');
    expect($table)->toBe('courses');
    $table = ProductableEnum::getTableFromType('seminar');
    expect($table)->toBe('seminars');
    $table = ProductableEnum::getTableFromType('digital_asset');
    expect($table)->toBe('digital_assets');
    $table = ProductableEnum::getTableFromType('non_existent_type');
    expect($table)->toBe('courses'); // default case
});
