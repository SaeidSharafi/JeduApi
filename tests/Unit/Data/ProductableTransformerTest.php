<?php

use App\Data\Admin\Course\ShowCourseData;
use App\Data\Transformer\ProductableTransformer;
use App\Models\Course;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;

beforeEach(function (): void {
    $this->mockProperty = Mockery::mock(DataProperty::class);
    $this->mockContext  = Mockery::mock(TransformationContext::class);
    Storage::fake('public');
});

describe('functional tests', function (): void {
    it('return null if value is null', function (): void {
        $transformer = new ProductableTransformer();
        $productable = $transformer->transform($this->mockProperty, null, $this->mockContext);
        expect($productable)->toBeNull();
    });

    it('throws an exception if value is not ProductableContract', function (): void {
        $transformer = new ProductableTransformer();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must implement ProductableContract, integer given.');

        $transformer->transform($this->mockProperty, 123, $this->mockContext);
    });

    it('transforms a collection of Course instances to CourseListItemData', function (): void {
        $transformer = new ProductableTransformer(short: true);
        $courses = Course::factory()
            ->count(3)
            ->withCategory()
            ->withMedia()
            ->create();

        $productables = $transformer->transform($this->mockProperty, $courses, $this->mockContext);

        expect($productables)->toBeInstanceOf(Illuminate\Support\Collection::class)
            ->and($productables->first())->toBeInstanceOf(App\Data\Admin\Course\CourseListItemData::class)
            ->and($productables->count())->toBe(3);
    });

    it('transforms a collection with mixed productable types throws an exception', function (): void {
        $transformer = new ProductableTransformer(short: true);
        $course = Course::factory()
            ->withCategory()
            ->withMedia()
            ->create();
        $invalidItem = new stdClass();
        $collection = collect([$course, $invalidItem]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must implement ProductableContract, object given.');

        $transformer->transform($this->mockProperty, $collection, $this->mockContext);
    });
});
describe('item transformations', function (): void {
    it('transforms a Course instance to ShowCourseData', function (): void {
        $transformer = new ProductableTransformer();
        $course      = Course::factory()
            ->withCategory()
            ->withMedia()
            ->create(
                ['short_name' => 'Test Course']
            );

        $productable = $transformer->transform($this->mockProperty, $course, $this->mockContext);

        expect($productable)->toBeInstanceOf(ShowCourseData::class)
            ->and($productable->short_name)->toBe('Test Course');
    });
    it('transforms a seminar instance to ShowSeminarData', function (): void {
        $transformer = new ProductableTransformer();
        $seminar     = App\Models\Seminar::factory()
            ->withCategory()
            ->withMedia()
            ->create(
                ['short_name' => 'Test Seminar']
            );
        $productable = $transformer->transform($this->mockProperty, $seminar, $this->mockContext);

        expect($productable)->toBeInstanceOf(App\Data\Admin\Seminar\ShowSeminarData::class)
            ->and($productable->short_name)->toBe('Test Seminar');
    });

    it('transforms a DigitalAsset instance to ShowDigitalAssetData', function (): void {
        $transformer  = new ProductableTransformer();
        $digitalAsset = App\Models\DigitalAsset::factory()
            ->withCategory()
            ->withFile()
            ->withMedia()
            ->create(
                ['name' => 'Test Digital Asset']
            );

        $productable = $transformer->transform($this->mockProperty, $digitalAsset, $this->mockContext);

        expect($productable)->toBeInstanceOf(App\Data\Admin\DigitalAsset\ShowDigitalAssetData::class)
            ->and($productable->name)->toBe('Test Digital Asset');
    });
    it('transforms a Course instance to CourseListItemData when short is true', function (): void {
        $transformer = new ProductableTransformer(short: true);
        $course      = Course::factory()
            ->withCategory()
            ->withMedia()
            ->create(
                ['short_name' => 'Test Course']
            );

        $productable = $transformer->transform($this->mockProperty, $course, $this->mockContext);

        expect($productable)->toBeInstanceOf(App\Data\Admin\Course\CourseListItemData::class)
            ->and($productable->short_name)->toBe('Test Course');
    });

    it('transforms a Seminar instance to SeminarListItemData when short is true', function (): void {
        $transformer = new ProductableTransformer(short: true);
        $seminar     = App\Models\Seminar::factory()
            ->withCategory()
            ->withMedia()
            ->create(
                ['short_name' => 'Test Seminar']
            );

        $productable = $transformer->transform($this->mockProperty, $seminar, $this->mockContext);

        expect($productable)->toBeInstanceOf(App\Data\Admin\Seminar\SeminarListItemData::class)
            ->and($productable->short_name)->toBe('Test Seminar');
    });

    it('transforms a DigitalAsset instance to DigitalAssetListItemData when short is true', function (): void {
        $transformer  = new ProductableTransformer(short: true);
        $digitalAsset = App\Models\DigitalAsset::factory()
            ->withCategory()
            ->withFile()
            ->withMedia()
            ->create(
                ['name' => 'Test Digital Asset']
            );

        $productable = $transformer->transform($this->mockProperty, $digitalAsset, $this->mockContext);

        expect($productable)->toBeInstanceOf(App\Data\Admin\DigitalAsset\DigitalAssetListItemData::class)
            ->and($productable->name)->toBe('Test Digital Asset');
    });

});


