<?php

declare(strict_types=1);

use App\Enums\MorphTypeEnum;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\assertDatabaseHas;

uses(Tests\AuthTestTrait::class);

beforeEach(function (): void {
    Illuminate\Http\UploadedFile::fake();
    Storage::fake('public');
});
it('can view list of courses', function (): void {
    $courses = App\Models\Course::factory(5)->create();

    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_VIEW_ANY->value,
    ]);
    $response = $this->getJson(route('api.v1.admin.course.index'));
    $response
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'slug',
                        'full_name',
                        'short_name',
                        'difficulty_level',
                        'additional_info',
                        'properties',
                        'status',
                        'categories',
                        'created_by',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ])
        ->assertJsonCount(5, 'data.data');

    $actualDataItems = collect($response->json('data.data'));

    foreach ($courses as $expectedCourse) {
        $match = $actualDataItems->first(function ($actualItem) use ($expectedCourse) {
            return $actualItem['slug'] === $expectedCourse->slug;
        });

        expect($match)->not->toBeNull("Expected course with slug '{$expectedCourse->slug}' not found or properties mismatch.");

        if ($match) {
            AssertableJson::fromArray($match)
                ->where('slug', $expectedCourse->slug)
                ->where('full_name', $expectedCourse->full_name)
                ->where('short_name', $expectedCourse->short_name)
                ->where('difficulty_level.value', $expectedCourse->difficulty_level->value)
                ->where('status.value', $expectedCourse->status->value)
                ->where('created_by', $expectedCourse->created_by)
                ->etc();
        }
    }
});

it('can create a new course with valid data', function (): void {
    $courseData = App\Models\Course::factory()->make();

    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_CREATE->value,
    ]);
    $file           = Illuminate\Http\UploadedFile::fake()->image('gallery.jpg');
    $uploadResponse = $this->postJson(route('api.v1.admin.media.upload'), [
        'file' => $file,
    ]);
    $uploadResponse->assertStatus(201);
    $mediaId = $uploadResponse->json('data.id');
    expect($mediaId)->not()->toBeNull();
    $categories = App\Models\Category::factory(2)->create()->pluck('id')->toArray();
    $cover      = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $response = $this->postJson(route('api.v1.admin.course.store'), [
        ...$courseData->toArray(),
        'categories' => $categories,
        'media'      => [
            'gallery'     => [$mediaId],
            'thumbnail'   => [],
            'cover'       => [$cover->id],
            'certificate' => [],
        ],
    ]);
    $response->assertStatus(201);

    assertDatabaseHas('courses', [
        'slug'        => $courseData->slug,
        'full_name'   => $courseData->full_name,
        'short_name'  => $courseData->short_name,
        'description' => $courseData->description,
        'duration'    => $courseData->duration,
    ]);
    $course = App\Models\Course::query()
        ->where('slug', $courseData->slug)
        ->first();
    assertDatabaseHas('media', [
        'id'  => $mediaId,
        'alt' => '',
    ]);
    assertDatabaseHas('mediables', [
        'media_id'      => $mediaId,
        'mediable_id'   => $course->id,
        'mediable_type' => MorphTypeEnum::COURSE->value,
        'tag'           => 'gallery',
    ]);
    assertDatabaseHas('categorizables', [
        'categorizable_id'   => $course->id,
        'categorizable_type' => MorphTypeEnum::COURSE->value,
        'category_id'        => $categories[0],
    ]);
    assertDatabaseHas('categorizables', [
        'categorizable_id'   => $course->id,
        'categorizable_type' => MorphTypeEnum::COURSE->value,
        'category_id'        => $categories[1],
    ]);
});

it('can not create a new course with invalid data', function (): void {
    $courseData = App\Models\Course::factory()->make([
        'slug'                 => null,
        'full_name'            => null, // Changed from name
        'short_name'           => null,
        'description'          => null,
        'default_teacher_info' => null,
        'meta_title'           => null,
        'meta_description'     => null,
        'meta_keywords'        => null,
        'status'               => null,
    ])->toArray();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_CREATE->value,
    ]);
    $response = $this->postJson(route('api.v1.admin.course.store'), $courseData);
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'slug',
            'full_name', // Changed from name
            'short_name',
            'status',
        ]);
});
it('can not create a new course with smiliar slug', function (): void {
    $course     = App\Models\Course::factory()->create();
    $courseData = App\Models\Course::factory()->make([
        'slug' => $course->slug,
    ])->toArray();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_CREATE->value,
    ]);
    $response = $this->postJson(route('api.v1.admin.course.store'), $courseData);
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'slug',
        ]);
});
it('can not create a new course with invalid slug', function (): void {
    $courseData = App\Models\Course::factory()->make([
        'slug' => 'invalid slug',
    ])->toArray();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_CREATE->value,
    ]);
    $response = $this->postJson(route('api.v1.admin.course.store'), $courseData);
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

it('can view a course', function (): void {
    $categories = App\Models\Category::factory(3)->create();

    $course = App\Models\Course::factory()
        ->withMedia(['cover'])
        ->create();
    $course->categories()->sync($categories);
    $cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('gallery.jpg'))
        ->toDisk('public')
        ->upload();
    $course->attachMedia($cover, 'cover');
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_VIEW->value,
    ]);
    $response = $this->getJson(route('api.v1.admin.course.show', $course->id));

    $media = $course->getMedia('cover')->first();

    $response
        ->assertStatus(200)
        ->assertJson(function (AssertableJson $response) use ($categories, $media, $course): void {
            $response
                ->where('data.id', $course->id)
                ->where('data.slug', $course->slug)
                ->where('data.full_name', $course->full_name)
                ->where('data.short_name', $course->short_name)
                ->where('data.duration', $course->duration)
                ->where('data.difficulty_level', [
                    'value' => $course->difficulty_level->value,
                    'label' => $course->difficulty_level->translate(),
                ])
                ->where('data.additional_info', $course->additional_info)
                ->where('data.properties', $course->properties)
                ->where('data.status', [
                    'value' => $course->status->value,
                    'label' => $course->status->translate(),
                ])
                ->where('data.categories', $categories->map(fn ($category): array => [
                    'id'     => $category->id,
                    'name'   => $category->name,
                    'slug'   => $category->slug,
                    'status' => [
                        'value' => $category->status->value,
                        'label' => $category->status->translate(),
                    ],
                    'image_url'  => $category->image_url,
                    'icon_url'   => $category->icon_url,
                    'created_by' => $category->created_by,
                    'created_at' => $category->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $category->updated_at?->format('Y-m-d H:i:s'),
                ]))
                ->has('data.media.gallery', 0)
                ->has('data.media.video', 0)
                ->has('data.media.cover', 1)
                ->where('data.media.cover.0.id', $media->id)
                ->where('data.media.cover.0.url', $media->getUrl())
                ->where('data.media.cover.0.size', $media->size)
                ->where('data.media.cover.0.file_name', $media->filename)
                ->where('data.media.cover.0.alt', $media->getAttribute('alt'))
                ->where('data.media.cover.0.mime_type', $media->mime_type)
                ->where('data.media.cover.0.extension', $media->extension)
                ->where('data.media.cover.0.tag', 'cover')
                ->has('data.media.certificate', 0)
                ->etc();
        });
});

it('can edit a course', function (): void {
    $course = App\Models\Course::factory()
        ->create();
    $courseData = App\Models\Course::factory()->make()->toArray();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_UPDATE->value,
    ]);

    $file           = Illuminate\Http\UploadedFile::fake()->image('gallery.jpg');
    $uploadResponse = $this->postJson(route('api.v1.admin.media.upload'), [
        'file' => $file,
        'alt'  => 'Test Alt',
    ]);
    $uploadResponse->assertStatus(201);
    $mediaId = $uploadResponse->json('data.id');
    expect($mediaId)->not()->toBeNull();
    $categories = App\Models\Category::factory(3)->create()->pluck('id')->toArray();

    $cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $response = $this->putJson(route('api.v1.admin.course.update', $course->id), [
        ...$courseData,
        'categories' => $categories,
        'media'      => [
            'gallery'     => [$mediaId],
            'thumbnail'   => [],
            'cover'       => [$cover->id],
            'certificate' => [],
        ],
    ]);
    $response->assertStatus(200);

    assertDatabaseHas('courses', [
        'id'          => $course->id,
        'slug'        => $courseData['slug'],
        'full_name'   => $courseData['full_name'],
        'short_name'  => $courseData['short_name'],
        'description' => $courseData['description'],
        'duration'    => $courseData['duration'],
    ]);

    assertDatabaseHas('media', [
        'id'  => $mediaId,
        'alt' => 'Test Alt',
    ]);
    assertDatabaseHas('mediables', [
        'media_id'      => $mediaId,
        'mediable_id'   => $course->id,
        'mediable_type' => MorphTypeEnum::COURSE->value,
        'tag'           => 'gallery',
    ]);
});
it('can pass slug unique check', function (): void {
    $course = App\Models\Course::factory()
        ->withCategory()
        ->create();
    $category   = App\Models\Category::factory()->create();
    $courseData = App\Models\Course::factory()->make(
        [
            'slug' => $course->slug,
        ]
    )->toArray();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_UPDATE->value,
    ]);
    $cover = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $response = $this->putJson(route('api.v1.admin.course.update', $course->id), [
        ...$courseData,
        'categories' => [$category->id],
        'media'      => [
            'gallery'     => [],
            'thumbnail'   => [],
            'cover'       => [$cover->id],
            'certificate' => [],
        ],
    ]);
    $response->assertSuccessful();

    assertDatabaseHas('courses', [
        'id'          => $course->id,
        'slug'        => $courseData['slug'],
        'full_name'   => $courseData['full_name'],
        'short_name'  => $courseData['short_name'],
        'description' => $courseData['description'],
        'duration'    => $courseData['duration'],
    ]);

});
it('can not edit a course with duplicate slug', function (): void {
    $course2    = App\Models\Course::factory()->create();
    $course     = App\Models\Course::factory()->create();
    $courseData = App\Models\Course::factory()->make(
        [
            'slug' => $course2->slug,
        ]
    )->toArray();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_UPDATE->value,
    ]);

    $response = $this->putJson(route('api.v1.admin.course.update', $course->id), [
        ...$courseData,
        'media' => [
            'gallery'     => [],
            'thumbnail'   => [],
            'cover'       => null,
            'certificate' => [],
        ],
    ]);
    $response->assertInvalid(['slug']);

});

it('can not edit a course with invalid data', function (): void {
    $course = App\Models\Course::factory()->create();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_UPDATE->value,
    ]);
    $courseData = App\Models\Course::factory()->make([
        'slug'                 => null,
        'full_name'            => null, // Changed from name
        'short_name'           => null,
        'description'          => null,
        'default_teacher_info' => null,
        'meta_title'           => null,
        'meta_description'     => null,
        'meta_keywords'        => null,
        'status'               => null,
    ])->toArray();
    $response = $this->putJson(route('api.v1.admin.course.update', $course->id), $courseData);
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'slug',
            'full_name', // Changed from name
            'short_name',
            'status',
        ]);
});

it('can not edit a course with invalid slug', function (): void {
    $course     = App\Models\Course::factory()->create();
    $courseData = App\Models\Course::factory()->make([
        'slug' => 'invalid slug',
    ])->toArray();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_UPDATE->value,
    ]);
    $response = $this->putJson(route('api.v1.admin.course.update', $course->id), $courseData);
    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'slug',
        ]);
});

it('can delete a course', function (): void {
    $this->media = MediaUploader::fromSource(Illuminate\Http\UploadedFile::fake()->image('gallery.jpg'))
        ->toDisk('public')
        ->upload();
    $course = App\Models\Course::factory()
        ->create();
    $course->attachMedia($this->media, 'gallery');
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_DELETE->value,
    ]);
    $response = $this->deleteJson(route('api.v1.admin.course.destroy', $course->id));
    $response->assertStatus(204);
    $this->assertDatabaseMissing('courses', [
        'id' => $course->id,
    ]);
    $this->assertDatabaseMissing('mediables', [
        'mediable_id'   => $course->id,
        'mediable_type' => MorphTypeEnum::COURSE->value,
    ]);
    $this->assertDatabaseMissing('media', [
        'id' => $this->media->id,
    ]);
});
