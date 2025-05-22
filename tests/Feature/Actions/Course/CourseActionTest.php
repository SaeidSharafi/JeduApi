<?php

declare(strict_types=1);

use App\Actions\Course\CreateCourseAction;
use App\Actions\Course\DeleteCourseAction;
use App\Actions\Course\UpdateCourseAction;
use App\Data\Course\CreateCourseData;
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
                'slug' => 'test-course',
                'full_name' => 'Test Course',
                'short_name' => 'TC',
                'status' => App\Enums\PublicationStatusEnum::DRAFT->value,
            ]
        )->toArray();
        $data = CreateCourseData::from([
            ...$courseData,
            'media' => [
                'gallery' => [$this->media->id],
                'video' => [$this->media->id],
                'thumbnail' => [$this->media->id],
                'certificate' => [$this->media->id],
            ],
        ]);

        $action = new CreateCourseAction();
        $action->handle($data);

        $course = Course::where('slug', 'test-course')
            ->withMedia()
            ->first();

        expect($course->media)->toHaveCount(4)
            ->and($course)->not()->toBeNull()
            ->and($course->full_name)->toBe('Test Course')
            ->and($course->short_name)->toBe('TC')
            ->and($course->status)->toBe(App\Enums\PublicationStatusEnum::DRAFT);
    });

    test('UpdateCourseAction successfully update course', function (): void {
        $course = Course::factory()->create();
        $courseData = Course::factory()->make(
            [
                'slug' => 'test-course',
                'full_name' => 'Updated Course Name',
                'short_name' => 'TC',
                'status' => App\Enums\PublicationStatusEnum::DRAFT->value,
            ]
        )->toArray();
        $data = CreateCourseData::from([
            ...$course->toArray(),
            'full_name' => 'Updated Course Name',
            'short_name' => 'UC',
            'status' => App\Enums\PublicationStatusEnum::PUBLISHED->value,
            'media' => [
                'gallery' => [$this->media2->id],
                'video' => [$this->media2->id],
                'thumbnail' => [$this->media2->id],
                'certificate' => [],
            ],
        ]);

        $action = new UpdateCourseAction();
        $action->handle($data, $course);

        $course->load('media')->refresh();

        expect($course)->not()->toBeNull()
            ->and($course->full_name)->toBe('Updated Course Name')
            ->and($course->short_name)->toBe('UC')
            ->and($course->status)->toBe(App\Enums\PublicationStatusEnum::PUBLISHED)
            ->and($course->media)->toHaveCount(3)
            ->and($course->media?->first()?->id)->toBe($this->media2->id);
    });

    test('DeleteCourseAction successfully delete course', function (): void {
        $course = Course::factory()->create([
            'full_name' => 'Course to be deleted',
            'short_name' => 'CTBD',
            'slug' => 'course-to-be-deleted',
        ]);

        $action = new DeleteCourseAction();
        $action->handle($course);

        $deletedCourse = Course::where('slug', 'course-to-be-deleted')->first();

        expect($deletedCourse)->toBeNull();
    });
});
