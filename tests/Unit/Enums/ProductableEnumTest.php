<?php

declare(strict_types=1);

use App\Enums\ProductableEnum;

it('return alias correctly', function () {
    $alias = ProductableEnum::getAlias(App\Models\Course::class);
    expect($alias)->toBe('course');
    $alias = ProductableEnum::getAlias(App\Models\DigitalAsset::class);
    expect($alias)->toBe('digital_asset');
    $alias = ProductableEnum::getAlias(App\Models\Seminar::class);
    expect($alias)->toBe('seminar');
    $alias = ProductableEnum::getAlias('non_existent_class');
    expect($alias)->toBeNull();
});

it('return model class correctly', function () {
    $modelClass = ProductableEnum::COURSE->getModelClass();
    expect($modelClass)->toBe(App\Models\Course::class);
    $modelClass = ProductableEnum::SEMINAR->getModelClass();
    expect($modelClass)->toBe(App\Models\Seminar::class);
    $modelClass = ProductableEnum::DIGITAL_ASSET->getModelClass();
    expect($modelClass)->toBe(App\Models\DigitalAsset::class);

});
