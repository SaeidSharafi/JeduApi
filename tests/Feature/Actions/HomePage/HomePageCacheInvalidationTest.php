<?php

declare(strict_types=1);

use App\Actions\Admin\Category\CreateCategoryAction;
use App\Actions\Admin\Category\DeleteCategoryAction;
use App\Actions\Admin\Category\UpdateCategoryAction;
use App\Actions\Admin\Course\CreateCourseAction;
use App\Actions\Admin\Course\DeleteCourseAction;
use App\Actions\Admin\Course\UpdateCourseAction;
use App\Actions\Admin\Partner\CreatePartnerAction;
use App\Actions\Admin\Partner\DeleteCPartnerAction;
use App\Actions\Admin\Partner\ForgetPartnerCachesAction;
use App\Actions\Admin\Partner\UpdatePartnerAction;
use App\Actions\Admin\Setting\StudentStory\CreateStudentStoryAction;
use App\Actions\Admin\Setting\StudentStory\DeleteStudentStoryAction;
use App\Actions\Admin\Setting\StudentStory\UpdateStudentStoryAction;
use App\Actions\Admin\Slider\CreateSliderAction;
use App\Actions\Admin\Slider\DeleteSliderAction;
use App\Actions\Admin\Slider\UpdateSliderAction;
use App\Actions\Admin\Slider\UpdateSliderStatusAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Category\CreateCategoryData;
use App\Data\Admin\ChangeStatusData;
use App\Data\Admin\Course\CreateCourseData;
use App\Data\Admin\Partner\PartnerCreateData;
use App\Data\Admin\Settings\StudentStory\StudentStoryCreateData;
use App\Data\Admin\Slider\SliderCreateData;
use App\Enums\Content\PartnerShowInEnum;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\System\CacheKey;
use App\Models\Course;
use App\Models\Slider;

covers(
    CreateSliderAction::class,
    UpdateSliderAction::class,
    UpdateSliderStatusAction::class,
    DeleteSliderAction::class,
    CreatePartnerAction::class,
    UpdatePartnerAction::class,
    DeleteCPartnerAction::class,
    ForgetPartnerCachesAction::class,
    CreateStudentStoryAction::class,
    UpdateStudentStoryAction::class,
    DeleteStudentStoryAction::class,
    CreateCourseAction::class,
    UpdateCourseAction::class,
    DeleteCourseAction::class,
    CreateCategoryAction::class,
    UpdateCategoryAction::class,
    DeleteCategoryAction::class,
);

beforeEach(function (): void {
    Storage::fake('public');

    $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('asset.jpg'))
        ->toDisk('public')
        ->upload();
    $this->cache = app(CacheStore::class);
});

test('slider writes drop the cached slider list', function (): void {
    $createData = SliderCreateData::from([
        'title'   => 'Slider',
        'caption' => null,
        'status'  => PublicationStatusEnum::PUBLISHED->value,
        'image'   => $this->media->id,
        'link'    => null,
        'order'   => 1,
    ]);

    $this->cache->put(CacheKey::Slider, [], ['stale']);
    $slider = app(CreateSliderAction::class)->handle($createData);
    expect($this->cache->get(CacheKey::Slider))->toBeNull();

    $updateData = SliderCreateData::from([
        'title'   => 'Updated Slider',
        'caption' => null,
        'status'  => PublicationStatusEnum::PUBLISHED->value,
        'image'   => $this->media->id,
        'link'    => null,
        'order'   => 2,
    ]);

    $this->cache->put(CacheKey::Slider, [], ['stale']);
    app(UpdateSliderAction::class)->handle($slider, $updateData);
    expect($this->cache->get(CacheKey::Slider))->toBeNull();

    $this->cache->put(CacheKey::Slider, [], ['stale']);
    app(UpdateSliderStatusAction::class)->handle(
        ChangeStatusData::from(['status' => PublicationStatusEnum::DRAFT->value]),
        $slider,
    );
    expect($this->cache->get(CacheKey::Slider))->toBeNull();

    $this->cache->put(CacheKey::Slider, [], ['stale']);
    app(DeleteSliderAction::class)->handle($slider);
    expect($this->cache->get(CacheKey::Slider))->toBeNull();
    expect(Slider::query()->find($slider->id))->toBeNull();
});

test('partner writes drop every cached partner list', function (): void {
    $partnerKeys = [CacheKey::PartnersInHome, CacheKey::PartnersInCourse, CacheKey::Partners];

    $warmPartnerCaches = function () use ($partnerKeys): void {
        foreach ($partnerKeys as $key) {
            $this->cache->put($key, [], ['stale']);
        }
    };

    $assertPartnerCachesCleared = function () use ($partnerKeys): void {
        foreach ($partnerKeys as $key) {
            expect($this->cache->get($key))->toBeNull();
        }
    };

    $createData = PartnerCreateData::from([
        'title'     => 'Partner',
        'caption'   => null,
        'image'     => $this->media->id,
        'url'       => null,
        'show_in'   => PartnerShowInEnum::HOME->value,
        'order'     => 1,
        'is_active' => true,
    ]);

    $warmPartnerCaches();
    $partner = app(CreatePartnerAction::class)->handle($createData);
    $assertPartnerCachesCleared();

    $updateData = PartnerCreateData::from([
        'title'     => 'Updated Partner',
        'caption'   => null,
        'image'     => $this->media->id,
        'url'       => null,
        'show_in'   => PartnerShowInEnum::COURSE->value,
        'order'     => 2,
        'is_active' => false,
    ]);

    $warmPartnerCaches();
    app(UpdatePartnerAction::class)->handle($partner, $updateData);
    $assertPartnerCachesCleared();

    $warmPartnerCaches();
    app(DeleteCPartnerAction::class)->handle($partner);
    $assertPartnerCachesCleared();
});

test('student story writes drop every cached story query', function (): void {
    $createData = StudentStoryCreateData::from([
        'student_name' => 'Student',
        'course_name'  => 'Course',
        'course_url'   => 'https://example.com/course',
        'story_text'   => 'Story text',
        'avatar'       => null,
        'is_visible'   => true,
        'categories'   => [],
        'courses'      => [],
    ]);

    $this->cache->put(CacheKey::StudentStory, ['hash' => 'hash'], ['stale']);
    $story = app(CreateStudentStoryAction::class)->handle($createData);
    expect($this->cache->get(CacheKey::StudentStory, ['hash' => 'hash']))->toBeNull();

    $updateData = StudentStoryCreateData::from([
        'student_name' => 'Updated Student',
        'course_name'  => 'Course',
        'course_url'   => 'https://example.com/course',
        'story_text'   => 'Updated story text',
        'avatar'       => null,
        'is_visible'   => true,
        'categories'   => [],
        'courses'      => [],
    ]);

    $this->cache->put(CacheKey::StudentStory, ['hash' => 'hash'], ['stale']);
    app(UpdateStudentStoryAction::class)->handle($story, $updateData);
    expect($this->cache->get(CacheKey::StudentStory, ['hash' => 'hash']))->toBeNull();

    $this->cache->put(CacheKey::StudentStory, ['hash' => 'hash'], ['stale']);
    app(DeleteStudentStoryAction::class)->handle($story);
    expect($this->cache->get(CacheKey::StudentStory, ['hash' => 'hash']))->toBeNull();
});

test('course writes drop every cached story query', function (): void {
    $courseData = fn (string $slug): CreateCourseData => CreateCourseData::from([
        'slug'                    => $slug,
        'full_name'               => 'Course '.$slug,
        'short_name'              => 'C',
        'description'             => null,
        'duration'                => null,
        'difficulty_level'        => CourseDifficultyLevelEnum::BEGINNER->value,
        'career_prospects_text'   => null,
        'curriculum_summary_text' => null,
        'outcomes_json'           => [],
        'default_teacher_info'    => null,
        'provides_certificate'    => false,
        'faq'                     => null,
        'additional_info'         => null,
        'meta_title'              => null,
        'meta_description'        => null,
        'meta_keywords'           => null,
        'properties'              => null,
        'status'                  => PublicationStatusEnum::PUBLISHED->value,
        'categories'              => [],
        'digital_assets'          => [],
        'media'                   => [],
    ]);

    $this->cache->put(CacheKey::StudentStory, ['hash' => 'hash'], ['stale']);
    app(CreateCourseAction::class)->handle($courseData('course-a'));
    expect($this->cache->get(CacheKey::StudentStory, ['hash' => 'hash']))->toBeNull();

    $course = Course::query()->where('slug', 'course-a')->firstOrFail();

    $this->cache->put(CacheKey::StudentStory, ['hash' => 'hash'], ['stale']);
    app(UpdateCourseAction::class)->handle($courseData('course-b'), $course);
    expect($this->cache->get(CacheKey::StudentStory, ['hash' => 'hash']))->toBeNull();

    $this->cache->put(CacheKey::StudentStory, ['hash' => 'hash'], ['stale']);
    app(DeleteCourseAction::class)->handle($course);
    expect($this->cache->get(CacheKey::StudentStory, ['hash' => 'hash']))->toBeNull();
});

test('category writes drop every cached story query', function (): void {
    $categoryData = fn (string $slug): CreateCategoryData => CreateCategoryData::from([
        'name'             => 'Category '.$slug,
        'slug'             => $slug,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
        'parent_id'        => null,
        'description'      => null,
        'color_scheme'     => null,
        'meta_title'       => 'Meta title',
        'meta_description' => 'Meta description that is long enough to satisfy the validation rule of the DTO.',
        'meta_keywords'    => null,
        'properties'       => null,
        'additional_info'  => null,
        'media'            => [],
    ]);

    $this->cache->put(CacheKey::StudentStory, ['hash' => 'hash'], ['stale']);
    app(CreateCategoryAction::class)->handle($categoryData('category-a'));
    expect($this->cache->get(CacheKey::StudentStory, ['hash' => 'hash']))->toBeNull();

    $category = App\Models\Category::query()->where('slug', 'category-a')->firstOrFail();

    $this->cache->put(CacheKey::StudentStory, ['hash' => 'hash'], ['stale']);
    app(UpdateCategoryAction::class)->handle($categoryData('category-b'), $category);
    expect($this->cache->get(CacheKey::StudentStory, ['hash' => 'hash']))->toBeNull();

    $this->cache->put(CacheKey::StudentStory, ['hash' => 'hash'], ['stale']);
    app(DeleteCategoryAction::class)->handle($category);
    expect($this->cache->get(CacheKey::StudentStory, ['hash' => 'hash']))->toBeNull();
});
