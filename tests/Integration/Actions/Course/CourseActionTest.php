<?php

declare(strict_types=1);

use App\Actions\Admin\Course\CreateCourseAction;
use App\Actions\Admin\Course\DeleteCourseAction;
use App\Actions\Admin\Course\UpdateCourseAction;
use App\Data\Admin\Course\CreateCourseData;
use App\Models\Course;

describe('CourseActionTest', function (): void {
    uses()->group('unit', 'actions', 'course');
    beforeEach(function (): void {
        Storage::fake('public');

        $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('course.jpg'))
            ->toDisk('public')
            ->upload();
        $this->media2 = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('course2.jpg'))
            ->toDisk('public')
            ->upload();
    });
    test('CreateCourseAction successfully create course', function (): void {

        Course::factory()->make()->toArray();
        $courseData = Course::factory()->make(
            [
                'slug'       => 'test-course',
                'full_name'  => 'Test Course',
                'short_name' => 'TC',
                'status'     => App\Enums\Content\PublicationStatusEnum::DRAFT->value,
            ]
        )->toArray();
        $ditialAssets = App\Models\DigitalAsset::factory(3)->create();
        $category     = App\Models\Category::factory()->create([
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);
        $data = CreateCourseData::from([
            ...$courseData,
            'categories'     => [$category->id],
            'digital_assets' => $ditialAssets->pluck('id')->toArray(),
            'media'          => [
                'gallery'     => [$this->media->id],
                'video'       => [$this->media->id],
                'cover'       => [$this->media->id],
                'certificate' => [$this->media->id],
            ],
        ]);

        $action = $this->app->make(CreateCourseAction::class);
        $action->handle($data);

        $course = Course::where('slug', 'test-course')
            ->withMedia()
            ->with('categories', 'digitalAssets')
            ->first();

        expect($course->media)->toHaveCount(4)
            ->and($course)->not()->toBeNull()
            ->and($course->full_name)->toBe('Test Course')
            ->and($course->short_name)->toBe('TC')
            ->and($course->thumbnail_url)->toBe($this->media->getUrl())
            ->and($course->status)->toBe(App\Enums\Content\PublicationStatusEnum::DRAFT)
            ->and($course->categories)->toHaveCount(1)
            ->and($course->categories->first()->name)->toBe('Test Category')
            ->and($course->digitalAssets)->toHaveCount(3)
            ->and($course->digitalAssets->first()->short_name)->toBeInCollection($ditialAssets, 'short_name');
    });

    test('UpdateCourseAction successfully update course', function (): void {
        $course = Course::factory()
            ->withCategory()
            ->withDigitalAssets()
            ->create()->fresh();
        $digitalAssetId = $course->digitalAssets->first()->id;
        $courseData     = Course::factory()->make(
            [
                'slug'       => 'test-course',
                'full_name'  => 'Updated Course Name',
                'short_name' => 'TC',
                'status'     => App\Enums\Content\PublicationStatusEnum::DRAFT->value,
            ]
        )->toArray();
        $ditialAssets = App\Models\DigitalAsset::factory(3)->create();

        $data = CreateCourseData::from([
            ...$course->toArray(),
            'full_name'      => 'Updated Course Name',
            'short_name'     => 'UC',
            'status'         => App\Enums\Content\PublicationStatusEnum::PUBLISHED->value,
            'digital_assets' => $ditialAssets->pluck('id')->toArray(),
            'categories'     => $course->categories->pluck('id')->toArray(),
            'media'          => [
                'gallery'     => [$this->media2->id],
                'video'       => [$this->media2->id],
                'cover'       => [$this->media2->id],
                'certificate' => [],
            ],
        ]);

        $action = $this->app->make(UpdateCourseAction::class);
        $action->handle($data, $course);

        $course->load('media')->refresh();

        expect($course)->not()->toBeNull()
            ->and($course->full_name)->toBe('Updated Course Name')
            ->and($course->short_name)->toBe('UC')
            ->and($course->thumbnail_url)->toBe($this->media2->getUrl())
            ->and($course->status)->toBe(App\Enums\Content\PublicationStatusEnum::PUBLISHED)
            ->and($course->media)->toHaveCount(3)
            ->and($course->media?->first()?->id)->toBe($this->media2->id);

        \Pest\Laravel\assertDatabaseHas('courses', [
            'slug'       => $course->slug,
            'full_name'  => 'Updated Course Name',
            'short_name' => 'UC',
            'status'     => App\Enums\Content\PublicationStatusEnum::PUBLISHED->value,
        ]);
        \Pest\Laravel\assertDatabaseHas('assetables', [
            'assetable_id'     => $course->id,
            'assetable_type'   => App\Enums\System\MorphTypeEnum::COURSE->value,
            'digital_asset_id' => $ditialAssets->first()->id,
        ]);

        \Pest\Laravel\assertDatabaseMissing('assetables', [
            'assetable_id'     => $course->id,
            'assetable_type'   => App\Enums\System\MorphTypeEnum::COURSE->value,
            'digital_asset_id' => $digitalAssetId,
        ]);

        \Pest\Laravel\assertDatabaseHas('categorizables', [
            'categorizable_id'   => $course->id,
            'categorizable_type' => App\Enums\System\MorphTypeEnum::COURSE->value,
            'category_id'        => $course->categories->first()->id,
        ]);
    });

    test('DeleteCourseAction successfully delete course', function (): void {
        $course = Course::factory()
            ->withCategory()
            ->withDigitalAssets()
            ->create([
                'full_name'  => 'Course to be deleted',
                'short_name' => 'CTBD',
                'slug'       => 'course-to-be-deleted',
            ]);

        $action = new DeleteCourseAction();
        $action->handle($course);

        $deletedCourse = Course::where('slug', 'course-to-be-deleted')->first();

        expect($deletedCourse)->toBeNull();

        \Pest\Laravel\assertDatabaseMissing('courses', [
            'slug'       => 'course-to-be-deleted',
            'full_name'  => 'Course to be deleted',
            'short_name' => 'CTBD',
        ]);
        \Pest\Laravel\assertDatabaseMissing('categorizables', [
            'categorizable_id'   => $course->id,
            'categorizable_type' => App\Enums\System\MorphTypeEnum::COURSE->value,
        ]);
        \Pest\Laravel\assertDatabaseMissing('assetables', [
            'assetable_id'   => $course->id,
            'assetable_type' => App\Enums\System\MorphTypeEnum::COURSE->value,
        ]);
    });
});
