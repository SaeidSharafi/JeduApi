<?php

declare(strict_types=1);

it('return alias correctly', function (): void {
    $alias = App\Enums\System\MorphTypeEnum::getAlias(App\Models\Category::class);
    expect($alias)->toBe('category');
    $alias = App\Enums\System\MorphTypeEnum::getAlias(App\Models\Course::class);
    expect($alias)->toBe('course');
    $alias = App\Enums\System\MorphTypeEnum::getAlias(App\Models\DigitalAsset::class);
    expect($alias)->toBe('digital_asset');
    $alias = App\Enums\System\MorphTypeEnum::getAlias(App\Models\Staff::class);
    expect($alias)->toBe('staff');
    $alias = App\Enums\System\MorphTypeEnum::getAlias(App\Models\User::class);
    expect($alias)->toBe('user');
    $alias = App\Enums\System\MorphTypeEnum::getAlias(App\Models\Teacher::class);
    expect($alias)->toBe('teacher');
    $alias = App\Enums\System\MorphTypeEnum::getAlias(App\Models\Vendor::class);
    expect($alias)->toBe('vendor');

    $alias = App\Enums\System\MorphTypeEnum::getAlias('non_existent_class');
    expect($alias)->toBeNull();
});

it('return model class correctly', function (): void {
    $modelClass = App\Enums\System\MorphTypeEnum::CATEGORY->getModelClass();
    expect($modelClass)->toBe(App\Models\Category::class);
    $modelClass = App\Enums\System\MorphTypeEnum::COURSE->getModelClass();
    expect($modelClass)->toBe(App\Models\Course::class);
    $modelClass = App\Enums\System\MorphTypeEnum::SEMINAR->getModelClass();
    expect($modelClass)->toBe(App\Models\Seminar::class);
    $modelClass = App\Enums\System\MorphTypeEnum::DIGITAL_ASSET->getModelClass();
    expect($modelClass)->toBe(App\Models\DigitalAsset::class);
    $modelClass = App\Enums\System\MorphTypeEnum::STAFF->getModelClass();
    expect($modelClass)->toBe(App\Models\Staff::class);
    $modelClass = App\Enums\System\MorphTypeEnum::USER->getModelClass();
    expect($modelClass)->toBe(App\Models\User::class);
    $modelClass = App\Enums\System\MorphTypeEnum::TEACHER->getModelClass();
    expect($modelClass)->toBe(App\Models\Teacher::class);
    $modelClass = App\Enums\System\MorphTypeEnum::VENDOR->getModelClass();
    expect($modelClass)->toBe(App\Models\Vendor::class);

});

it('return morph map correctly', function (): void {
    $morphMap = App\Enums\System\MorphTypeEnum::forMorphMap();
    expect($morphMap)->toBeArray()
        ->toHaveKey('category', App\Models\Category::class)
        ->toHaveKey('course', App\Models\Course::class)
        ->toHaveKey('seminar', App\Models\Seminar::class)
        ->toHaveKey('digital_asset', App\Models\DigitalAsset::class)
        ->toHaveKey('staff', App\Models\Staff::class)
        ->toHaveKey('teacher', App\Models\Teacher::class)
        ->toHaveKey('vendor', App\Models\Vendor::class)
        ->toHaveKey('product', App\Models\Product::class)
        ->toHaveKey('user', App\Models\User::class)
        ->toHaveKey('order', App\Models\Order::class)
        ->toHaveKey('refund', App\Models\Refund::class);
});

it('return categorizable types correctly', function (): void {
    $categorizables = App\Enums\System\MorphTypeEnum::getCategorizables(false);
    expect($categorizables)->toBeArray()
        ->toHaveCount(4)
        ->toContain(App\Enums\System\MorphTypeEnum::COURSE)
        ->toContain(App\Enums\System\MorphTypeEnum::SEMINAR)
        ->toContain(App\Enums\System\MorphTypeEnum::DIGITAL_ASSET)
        ->toContain(App\Enums\System\MorphTypeEnum::PRODUCT);
});

it('return only good start allowed categorizable types correctly', function (): void {
    $categorizables = App\Enums\System\MorphTypeEnum::getCategorizables(true);
    expect($categorizables)->toBeArray()
        ->toHaveCount(1)
        ->toContain(App\Enums\System\MorphTypeEnum::COURSE);
});

it('return enum from model class correctly', function (): void {
    $enum = App\Enums\System\MorphTypeEnum::fromModelClass(App\Models\Category::class);
    expect($enum)->toBe(App\Enums\System\MorphTypeEnum::CATEGORY);

    $enum = App\Enums\System\MorphTypeEnum::fromModelClass(App\Models\Product::class);
    expect($enum)->toBe(App\Enums\System\MorphTypeEnum::PRODUCT);

    $enum = App\Enums\System\MorphTypeEnum::fromModelClass('NonExistentClass');
    expect($enum)->toBeNull();
});
