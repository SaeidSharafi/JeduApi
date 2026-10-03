<?php

declare(strict_types=1);

use App\Data\Casts\ReviewableCast;

beforeEach(function (): void {
    $this->mockProperty = Mockery::mock(Spatie\LaravelData\Support\DataProperty::class);
    $this->mockContext  = Mockery::mock(Spatie\LaravelData\Support\Creation\CreationContext::class);
    Storage::fake('public');
});
it('retunr null if value is null', function (): void {
    $caster = new ReviewableCast();

    $producatable = $caster->cast($this->mockProperty, null, [], $this->mockContext);
    expect($producatable)->toBeNull();
});

it('throws an exception if value is not ReviewableContract', function (): void {
    $caster = new ReviewableCast();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Value must implement ReviewableContract, integer given.');

    $caster->cast($this->mockProperty, 123, [], $this->mockContext);
});

it('casts a Course instance to ShowCourseData', function (): void {
    $caster = new ReviewableCast();
    $course = App\Models\Course::factory()
        ->withCategory()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Course']
        );

    $productable = $caster->cast($this->mockProperty, $course, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Admin\Course\ShowCourseData::class)
        ->and($productable->short_name)->toBe('Test Course');
});
it('casts a Seminar instance to ShowSeminarData', function (): void {
    $caster  = new ReviewableCast();
    $seminar = App\Models\Seminar::factory()
        ->withCategory()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Seminar']
        );
    $productable = $caster->cast($this->mockProperty, $seminar, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Admin\Seminar\ShowSeminarData::class)
        ->and($productable->short_name)->toBe('Test Seminar');
});
it('casts a DigitalAsset instance to ShowDigitalAssetData', function (): void {
    $caster       = new ReviewableCast();
    $digitalAsset = App\Models\DigitalAsset::factory()
        ->withCategory()
        ->withFile()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Digital Asset']
        );

    $productable = $caster->cast($this->mockProperty, $digitalAsset, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Admin\DigitalAsset\ShowDigitalAssetData::class)
        ->and($productable->short_name)->toBe('Test Digital Asset');
});

it('casts a Course instance to CourseListItemData when short is true', function (): void {
    $caster = new ReviewableCast(true);
    $course = App\Models\Course::factory()
        ->withCategory()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Course']
        );

    $productable = $caster->cast($this->mockProperty, $course, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Admin\Course\CourseListItemData::class)
        ->and($productable->short_name)->toBe('Test Course');
});
it('casts a Seminar instance to SeminarListItemData when short is true', function (): void {
    $caster  = new ReviewableCast(true);
    $seminar = App\Models\Seminar::factory()
        ->withCategory()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Seminar']
        );

    $productable = $caster->cast($this->mockProperty, $seminar, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Admin\Seminar\SeminarListItemData::class)
        ->and($productable->short_name)->toBe('Test Seminar');
});
it('casts a DigitalAsset instance to DigitalAssetListItemData when short is true', function (): void {
    $caster       = new ReviewableCast(true);
    $digitalAsset = App\Models\DigitalAsset::factory()
        ->withCategory()
        ->withFile()
        ->withMedia()
        ->create(
            ['short_name' => 'Test Digital Asset']
        );

    $productable = $caster->cast($this->mockProperty, $digitalAsset, [], $this->mockContext);

    expect($productable)->toBeInstanceOf(App\Data\Admin\DigitalAsset\DigitalAssetListItemData::class)
        ->and($productable->short_name)->toBe('Test Digital Asset');
});

it('throws an exception for unsupported productable type', function (): void {
    $caster                 = new ReviewableCast();
    $unsupportedProductable = new class
    {
        // This class does not implement ReviewableContract
    };

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Value must implement ReviewableContract, '.gettype($unsupportedProductable).' given.');

    $caster->cast($this->mockProperty, $unsupportedProductable, [], $this->mockContext);
});
