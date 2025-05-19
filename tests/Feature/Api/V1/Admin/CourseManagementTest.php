<?php

declare(strict_types=1);

use Illuminate\Testing\Fluent\AssertableJson;
use function Pest\Laravel\assertDatabaseHas;

uses(Tests\AuthTestTrait::class);

beforeEach(function () {
    \Illuminate\Http\UploadedFile::fake();
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
                        'full_name', // Changed from name
                        'short_name',
                        'description',
                        'default_teacher_info',
                        'meta_title',
                        'meta_description',
                        'meta_keywords',
                        'status',
                        'media'
                    ],
                ],
            ],
        ]);
});

it('can create a new course with valida data', function (): void {
    $courseData = App\Models\Course::factory()->make()->toArray();
    $course = App\Data\Course\CourseData::from($courseData);
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_CREATE->value,
    ]);
    $file = \Illuminate\Http\UploadedFile::fake()->image('cover.jpg');
    $uploadResponse = $this->postJson(route('api.v1.admin.media.upload'), [
        'file' => $file,
    ]);
    $uploadResponse->assertStatus(201);
    $mediaId = $uploadResponse->json('data.id');
    expect($mediaId)->not()->toBeNull();
    $response = $this->postJson(route('api.v1.admin.course.store'), [
        ...$courseData,
        'media' => [
            'gallery'     => [$mediaId],
            'thumbnail'   => [],
            'cover'       => [],
            'certificate' => [],
        ],
    ]);
    $response->assertStatus(201);
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
    $course = App\Models\Course::factory()->create();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_VIEW->value,
    ]);
    $response = $this->getJson(route('api.v1.admin.course.show', $course->id));
    $response
        ->assertStatus(200)
        ->assertJson(function (AssertableJson $response) use ($course) {
            $response->where('data', [
                "slug"                    => $course->slug,
                "full_name"               => $course->full_name,
                "short_name"              => $course->short_name,
                "description"             => $course->description,
                "duration"                => $course->duration,
                "difficulty_level"        => [
                    "value" => $course->difficulty_level->value,
                    "label" => $course->difficulty_level->translate(),
                ],
                "career_prospects_text"   => $course->career_prospects_text,
                "curriculum_summary_text" => $course->curriculum_summary_text,
                "outcomes_json"           => $course->outcomes_json,
                "default_teacher_info"    => $course->default_teacher_info,
                "additional_info"         => $course->additional_info,
                "meta_title"              => $course->meta_title,
                "meta_description"        => $course->meta_description,
                "meta_keywords"           => $course->meta_keywords,
                "properties"              => $course->properties,
                "status"                  => [
                    "value" => $course->status->value,
                    "label" => $course->status->translate(),
                ],
                "media"                   => [
                    "gallery"     => [],
                    "video"       => [],
                    "cover"       => [],
                    "certificate" => []
                ]
            ])->etc();
        });
});

it('can edit a course', function (): void {
    $course = App\Models\Course::factory()->create();
    $courseData = App\Models\Course::factory()->make()->toArray();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_UPDATE->value,
    ]);
    $response = $this->getJson(route('api.v1.admin.course.edit', $course->id));
    $response->assertSuccessful()
        ->assertJson(function (AssertableJson $response) use ($course) {
            $response->where('data', [
                    'slug'                         => $course->slug,
                    'full_name'                    => $course->full_name,
                    'short_name'                   => $course->short_name,
                    'description'                  => $course->description,
                    'duration' => $course->duration,
                    'difficulty_level'             =>  $course->difficulty_level->value,
                    'career_prospects_text'        => $course->career_prospects_text,
                    'curriculum_summary_text'      => $course->curriculum_summary_text,
                    'outcomes_json'                => $course->outcomes_json,
                    'default_teacher_info'         => $course->default_teacher_info,
                    'additional_info'              => $course->additional_info,
                    'meta_title'                   => $course->meta_title,
                    'meta_description'             => $course->meta_description,
                    'meta_keywords'                => $course->meta_keywords,
                    'properties'                   => $course->properties,
                    'status'                       =>$course->status->value,
                    "media"                   => [
                        "gallery"     => [],
                        "video"       => [],
                        "cover"       => [],
                        "certificate" => []
                    ]
            ])->etc();
        });
    $file = \Illuminate\Http\UploadedFile::fake()->image('cover.jpg');
    $uploadResponse = $this->postJson(route('api.v1.admin.media.upload'), [
        'file' => $file,
        'alt'  => 'Test Alt',
    ]);
    $uploadResponse->assertStatus(201);
    $mediaId = $uploadResponse->json('data.id');
    expect($mediaId)->not()->toBeNull();
    $response = $this->putJson(route('api.v1.admin.course.update', $course->id), [
        ...$courseData,
        'media' => [
            'gallery'     => [$mediaId],
            'thumbnail'   => [],
            'cover'       => null,
            'certificate' => [],
        ],
    ]);
    $response->assertStatus(200);

    assertDatabaseHas('courses', [
        'id' => $course->id,
        'slug' => $courseData['slug'],
        'full_name' => $courseData['full_name'],
        'short_name' => $courseData['short_name'],
        'description' => $courseData['description'],
        'duration' => $courseData['duration'],
    ]);

    assertDatabaseHas('media', [
        'id' => $mediaId,
        'alt' => 'Test Alt',
    ]);
    assertDatabaseHas('mediables', [
        'media_id' => $mediaId,
        'mediable_id' => $course->id,
        'mediable_type' => \App\Models\Course::class,
        'tag' => 'gallery',
    ]);
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
    $course = App\Models\Course::factory()->create();
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
    $course = App\Models\Course::factory()->create();
    $this->authorized_user([
        App\Enums\PermissionEnum::COURSE_DELETE->value,
    ]);
    $response = $this->deleteJson(route('api.v1.admin.course.destroy', $course->id));
    $response->assertStatus(204);
    $this->assertDatabaseMissing('courses', [
        'id' => $course->id,
    ]);
});
