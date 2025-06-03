<?php

declare(strict_types=1);

it('return alias correctly', function () {
    $alias = App\Enums\MorphTypeEnum::getAlias(App\Models\Category::class);
    expect($alias)->toBe('category');
    $alias = App\Enums\MorphTypeEnum::getAlias(App\Models\Course::class);
    expect($alias)->toBe('course');
    $alias = App\Enums\MorphTypeEnum::getAlias(App\Models\DigitalAsset::class);
    expect($alias)->toBe('digital_asset');
    $alias = App\Enums\MorphTypeEnum::getAlias(App\Models\Staff::class);
    expect($alias)->toBe('staff');
    $alias = App\Enums\MorphTypeEnum::getAlias(App\Models\User::class);
    expect($alias)->toBe('user');

    $alias = App\Enums\MorphTypeEnum::getAlias('non_existent_class');
    expect($alias)->toBeNull();
});

it('return model class correctly', function () {
    $modelClass = App\Enums\MorphTypeEnum::CATEGORY->getModelClass();
    expect($modelClass)->toBe(App\Models\Category::class);
    $modelClass = App\Enums\MorphTypeEnum::COURSE->getModelClass();
    expect($modelClass)->toBe(App\Models\Course::class);
    $modelClass = App\Enums\MorphTypeEnum::SEMINAR->getModelClass();
    expect($modelClass)->toBe(App\Models\Seminar::class);
    $modelClass = App\Enums\MorphTypeEnum::DIGITAL_ASSET->getModelClass();
    expect($modelClass)->toBe(App\Models\DigitalAsset::class);
    $modelClass = App\Enums\MorphTypeEnum::STAFF->getModelClass();
    expect($modelClass)->toBe(App\Models\Staff::class);
    $modelClass = App\Enums\MorphTypeEnum::USER->getModelClass();
    expect($modelClass)->toBe(App\Models\User::class);

});

it('return morph map correctly', function () {
    $morphMap = App\Enums\MorphTypeEnum::forMorphMap();
    expect($morphMap)->toBeArray()
        ->toHaveCount(6)
        ->toHaveKey('category', App\Models\Category::class)
        ->toHaveKey('course', App\Models\Course::class)
        ->toHaveKey('seminar', App\Models\Seminar::class)
        ->toHaveKey('digital_asset', App\Models\DigitalAsset::class)
        ->toHaveKey('staff', App\Models\Staff::class)
        ->toHaveKey('user', App\Models\User::class);
});
