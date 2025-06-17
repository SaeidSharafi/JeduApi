<?php

declare(strict_types=1);

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);
beforeEach(function () {
    $this->mockProperty = Mockery::mock(Spatie\LaravelData\Support\DataProperty::class);
    $this->mockContext  = Mockery::mock(Spatie\LaravelData\Support\Creation\CreationContext::class);
    Storage::fake('public');
});
it('retunr null if value is null', function () {
    $caster = new App\Data\Casts\ProductableCast();

    $producatable = $caster->cast($this->mockProperty, null, [], $this->mockContext);
    expect($producatable)->toBeNull();
});

it('throws an exception if value is not ProductableContract', function () {
    $caster = new App\Data\Casts\ProductableCast();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Value must implement ProductableContract, integer given.');

    $caster->cast($this->mockProperty, 123, [], $this->mockContext);
});

it('casts a Course instance to ShowCourseData', function () {
    $caster = new App\Data\Casts\ProductableCast();
    $course = App\Models\Course::factory()
        ->withCategory()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Course']
        );

    $productable = $caster->cast($this->mockProperty, $course, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Course\ShowCourseData::class)
        ->and($productable->short_name)->toBe('Test Course');
});
it('casts a Seminar instance to ShowSeminarData', function () {
    $caster  = new App\Data\Casts\ProductableCast();
    $seminar = App\Models\Seminar::factory()
        ->withCategory()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Seminar']
        );
    $productable = $caster->cast($this->mockProperty, $seminar, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Seminar\ShowSeminarData::class)
        ->and($productable->short_name)->toBe('Test Seminar');
});
it('casts a DigitalAsset instance to ShowDigitalAssetData', function () {
    $caster       = new App\Data\Casts\ProductableCast();
    $digitalAsset = App\Models\DigitalAsset::factory()
        ->withCategory()
        ->withFile()
        ->withMedia()
        ->create(
            ['name' => 'Test Digital Asset']
        );

    $productable = $caster->cast($this->mockProperty, $digitalAsset, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\DigitalAsset\ShowDigitalAssetData::class)
        ->and($productable->name)->toBe('Test Digital Asset');
});

it('casts a Course instance to CourseListItemData when short is true', function () {
    $caster = new App\Data\Casts\ProductableCast(true);
    $course = App\Models\Course::factory()
        ->withCategory()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Course']
        );

    $productable = $caster->cast($this->mockProperty, $course, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Course\CourseListItemData::class)
        ->and($productable->short_name)->toBe('Test Course');
});
it('casts a Seminar instance to SeminarListItemData when short is true', function () {
    $caster  = new App\Data\Casts\ProductableCast(true);
    $seminar = App\Models\Seminar::factory()
        ->withCategory()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Seminar']
        );

    $productable = $caster->cast($this->mockProperty, $seminar, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Seminar\SeminarListItemData::class)
        ->and($productable->short_name)->toBe('Test Seminar');
});
it('casts a DigitalAsset instance to DigitalAssetListItemData when short is true', function () {
    $caster       = new App\Data\Casts\ProductableCast(true);
    $digitalAsset = App\Models\DigitalAsset::factory()
        ->withCategory()
        ->withFile()
        ->withMedia()
        ->create(
            ['name' => 'Test Digital Asset']
        );

    $productable = $caster->cast($this->mockProperty, $digitalAsset, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\DigitalAsset\DigitalAssetListItemData::class)
        ->and($productable->name)->toBe('Test Digital Asset');
});

it('throws an exception for unsupported productable type', function () {
    $caster                 = new App\Data\Casts\ProductableCast();
    $unsupportedProductable = new class
    {
        // This class does not implement ProductableContract
    };

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Value must implement ProductableContract, '.gettype($unsupportedProductable).' given.');

    $caster->cast($this->mockProperty, $unsupportedProductable, [], $this->mockContext);
});
