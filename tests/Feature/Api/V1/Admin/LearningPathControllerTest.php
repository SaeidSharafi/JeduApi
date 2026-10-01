<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Api\Admin\LearningPath\ArchiveLearningPathController;
use App\Http\Controllers\Api\Admin\LearningPath\LearningPathController;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\LearningPath;
use App\Models\Seminar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;

use function Pest\Laravel\assertDatabaseHas;

covers(
    LearningPathController::class,
    ArchiveLearningPathController::class,
);

uses(Tests\Support\Traits\AuthTestTrait::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function learningPathPayload(array $overrides = []): array
{
    $cover = MediaUploader::fromSource(UploadedFile::fake()->image('cover.jpg'))
        ->toDisk('public')
        ->upload();
    $gallery = MediaUploader::fromSource(UploadedFile::fake()->image('gallery.jpg'))
        ->toDisk('public')
        ->upload();

    return [
        'title'                    => 'Backend Engineering Path',
        'slug'                     => 'backend-engineering-path',
        'description'              => 'A guided path for backend engineering.',
        'introduction_title'       => 'Start here',
        'introduction_description' => 'Begin with the foundations and follow the path in order.',
        'conclusion_title'         => 'Keep building',
        'conclusion_description'   => 'Finish the path by applying what you learned.',
        'meta_title'               => 'Backend Engineering Learning Path',
        'meta_description'         => 'A practical backend engineering learning path for developers.',
        'meta_keywords'            => 'backend, laravel, engineering',
        'display_order'            => 2,
        'status'                   => 'draft',
        'media'                    => [
            'cover'   => [$cover->id],
            'gallery' => [$gallery->id],
        ],
        'steps' => [],
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function learningPathStep(int $position, string $type, int $id, string $title = 'Learn the topic'): array
{
    return [
        'position'         => $position,
        'productable_type' => $type,
        'productable_id'   => $id,
        'title'            => $title,
        'description'      => "Description for {$title}.",
    ];
}

it('creates an ordered draft with productable references, seo, and media', function (): void {
    Storage::fake('public');
    $cover = MediaUploader::fromSource(UploadedFile::fake()->image('path-cover.jpg'))
        ->toDisk('public')
        ->upload();
    $gallery = MediaUploader::fromSource(UploadedFile::fake()->image('path-gallery.jpg'))
        ->toDisk('public')
        ->upload();
    $course       = Course::factory()->create();
    $seminar      = Seminar::factory()->create();
    $digitalAsset = DigitalAsset::factory()->create();
    $products     = DB::table('products')->count();
    $options      = DB::table('product_delivery_options')->count();

    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);

    $response = $this->postJson(route('api.v1.admin.learning-paths.store'), learningPathPayload([
        'media' => [
            'cover'   => [$cover->id],
            'gallery' => [$gallery->id],
        ],
        'steps' => [
            learningPathStep(3, 'digital_asset', $digitalAsset->id, 'Build the asset'),
            learningPathStep(1, 'course', $course->id, 'Learn the course'),
            learningPathStep(2, 'seminar', $seminar->id, 'Attend the seminar'),
        ],
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'backend-engineering-path')
        ->assertJsonPath('data.meta_title', 'Backend Engineering Learning Path')
        ->assertJsonPath('data.media.cover.0.id', $cover->id)
        ->assertJsonPath('data.media.gallery.0.id', $gallery->id)
        ->assertJsonPath('data.steps.0.position', 1)
        ->assertJsonPath('data.steps.0.productable.type', 'course')
        ->assertJsonPath('data.steps.0.productable.id', $course->id)
        ->assertJsonPath('data.steps.1.position', 2)
        ->assertJsonPath('data.steps.2.position', 3);

    $path = LearningPath::query()->where('slug', 'backend-engineering-path')->firstOrFail();
    expect($path->steps->pluck('productable_type')->all())
        ->toBe(['course', 'seminar', 'digital_asset']);

    assertDatabaseHas('learning_paths', [
        'id'                 => $path->id,
        'introduction_title' => 'Start here',
        'conclusion_title'   => 'Keep building',
        'meta_keywords'      => 'backend, laravel, engineering',
        'display_order'      => 2,
        'status'             => 'draft',
        'thumbnail_url'      => $cover->getUrl(),
    ]);
    assertDatabaseHas('learning_path_steps', [
        'learning_path_id' => $path->id,
        'position'         => 2,
        'productable_type' => 'seminar',
        'productable_id'   => $seminar->id,
    ]);
    assertDatabaseHas('mediables', [
        'media_id'      => $cover->id,
        'mediable_id'   => $path->id,
        'mediable_type' => 'learning_path',
        'tag'           => 'cover',
    ]);
    expect(DB::table('products')->count())->toBe($products)
        ->and(DB::table('product_delivery_options')->count())->toBe($options);
});

it('allows an empty draft', function (): void {
    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);

    $response = $this->postJson(
        route('api.v1.admin.learning-paths.store'),
        learningPathPayload(['steps' => []]),
    );

    $response->assertCreated();

    $path = LearningPath::query()->where('slug', 'backend-engineering-path')->firstOrFail();
    expect($path->steps)->toBeEmpty();
});

it('does not allow an archived path to be created directly', function (): void {
    $course = Course::factory()->create();
    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);

    $this->postJson(
        route('api.v1.admin.learning-paths.store'),
        learningPathPayload([
            'status' => PublicationStatusEnum::ARCHIVED->value,
            'steps'  => [learningPathStep(1, 'course', $course->id)],
        ]),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', __('validation.in', ['attribute' => 'status']));

    $this->assertDatabaseMissing('learning_paths', ['slug' => 'backend-engineering-path']);
});

it('rejects a published path when steps are omitted', function (): void {
    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);
    $payload = learningPathPayload(['status' => 'published']);
    unset($payload['steps']);

    $this->postJson(route('api.v1.admin.learning-paths.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['steps']);
});

it('requires cover and gallery media on create and update', function (): void {
    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);
    $createPayload = learningPathPayload();
    unset($createPayload['media']['cover']);

    $this->postJson(route('api.v1.admin.learning-paths.store'), $createPayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media.cover']);

    $path = LearningPath::factory()->create();
    $this->authorized_user([PermissionEnum::LEARNING_PATH_UPDATE->value]);
    $updatePayload = learningPathPayload();
    unset($updatePayload['media']['gallery']);

    $this->putJson(route('api.v1.admin.learning-paths.update', $path), $updatePayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media.gallery']);
});

it('publishes a draft with an unpublished productable reference', function (): void {
    $course = Course::factory()->create(['status' => PublicationStatusEnum::DRAFT]);
    $path   = LearningPath::factory()->create();

    $this->authorized_user([PermissionEnum::LEARNING_PATH_UPDATE->value]);

    $this->putJson(
        route('api.v1.admin.learning-paths.update', $path),
        learningPathPayload([
            'status' => PublicationStatusEnum::PUBLISHED->value,
            'steps'  => [learningPathStep(1, 'course', $course->id)],
        ]),
    )
        ->assertOk()
        ->assertJsonPath('data.status', PublicationStatusEnum::PUBLISHED->value);

    expect($path->refresh()->status)->toBe(PublicationStatusEnum::PUBLISHED);
});

it('rejects publishing an empty draft', function (): void {
    $path = LearningPath::factory()->create();

    $this->authorized_user([PermissionEnum::LEARNING_PATH_UPDATE->value]);

    $this->putJson(
        route('api.v1.admin.learning-paths.update', $path),
        learningPathPayload(['status' => PublicationStatusEnum::PUBLISHED->value, 'steps' => []]),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['steps'])
        ->assertJsonPath('errors.steps.0', 'A learning path must contain at least one step unless it is a draft.');

    expect($path->refresh()->status)->toBe(PublicationStatusEnum::DRAFT);
});

it('allows direct edits to published paths', function (): void {
    $course = Course::factory()->create();
    $path   = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $path->steps()->create(learningPathStep(1, 'course', $course->id));

    $this->authorized_user([PermissionEnum::LEARNING_PATH_UPDATE->value]);

    $this->putJson(
        route('api.v1.admin.learning-paths.update', $path),
        learningPathPayload([
            'title'  => 'Updated Published Path',
            'status' => PublicationStatusEnum::PUBLISHED->value,
            'steps'  => [learningPathStep(1, 'course', $course->id, 'Updated course')],
        ]),
    )->assertOk();

    expect($path->refresh()->title)->toBe('Updated Published Path')
        ->and($path->steps->first()->title)->toBe('Updated course');
});

it('archives a published path and keeps it visible to staff', function (): void {
    $course = Course::factory()->create();
    $path   = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $path->steps()->create(learningPathStep(1, 'course', $course->id));

    $this->authorized_user([
        PermissionEnum::LEARNING_PATH_UPDATE->value,
        PermissionEnum::LEARNING_PATH_VIEW->value,
        PermissionEnum::LEARNING_PATH_VIEW_ANY->value,
    ]);

    $this->postJson(route('api.v1.admin.learning-paths.archive', $path))
        ->assertOk()
        ->assertJsonPath('data.status', PublicationStatusEnum::ARCHIVED->value);

    expect($path->refresh()->status)->toBe(PublicationStatusEnum::ARCHIVED);

    $this->getJson(route('api.v1.admin.learning-paths.show', $path))
        ->assertOk()
        ->assertJsonPath('data.status', PublicationStatusEnum::ARCHIVED->value);
    $this->getJson(route('api.v1.admin.learning-paths.index'))
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $path->id)
        ->assertJsonPath('data.data.0.status', PublicationStatusEnum::ARCHIVED->value);
});

it('rejects archiving a draft and reopening an archived path', function (): void {
    $draft = LearningPath::factory()->create();

    $this->authorized_user([PermissionEnum::LEARNING_PATH_UPDATE->value]);

    $this->postJson(route('api.v1.admin.learning-paths.archive', $draft))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', 'Only published learning paths can be archived.');

    $courseForDraftArchive = Course::factory()->create();
    $this->putJson(
        route('api.v1.admin.learning-paths.update', $draft),
        learningPathPayload([
            'status' => PublicationStatusEnum::ARCHIVED->value,
            'steps'  => [learningPathStep(1, 'course', $courseForDraftArchive->id)],
        ]),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', 'Only published learning paths can be archived.');

    $course   = Course::factory()->create();
    $archived = LearningPath::factory()->create(['status' => PublicationStatusEnum::ARCHIVED]);
    $archived->steps()->create(learningPathStep(1, 'course', $course->id));

    $this->putJson(
        route('api.v1.admin.learning-paths.update', $archived),
        learningPathPayload([
            'status' => PublicationStatusEnum::PUBLISHED->value,
            'steps'  => [learningPathStep(1, 'course', $course->id)],
        ]),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', 'An archived learning path cannot return to another lifecycle state.');

    expect($archived->refresh()->status)->toBe(PublicationStatusEnum::ARCHIVED);
});

it('requires the archive operation to retire a published path', function (): void {
    $course    = Course::factory()->create();
    $published = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $published->steps()->create(learningPathStep(1, 'course', $course->id));

    $this->authorized_user([PermissionEnum::LEARNING_PATH_UPDATE->value]);

    $this->putJson(
        route('api.v1.admin.learning-paths.update', $published),
        learningPathPayload([
            'status' => PublicationStatusEnum::ARCHIVED->value,
            'steps'  => [learningPathStep(1, 'course', $course->id)],
        ]),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', 'Use the archive operation to retire a published learning path.');

    expect($published->refresh()->status)->toBe(PublicationStatusEnum::PUBLISHED);
});

it('does not delete an archived learning path', function (): void {
    $path = LearningPath::factory()->create(['status' => PublicationStatusEnum::ARCHIVED]);

    $this->authorized_user([PermissionEnum::LEARNING_PATH_DELETE->value]);

    $this->deleteJson(route('api.v1.admin.learning-paths.destroy', $path))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['learning_path'])
        ->assertJsonPath('errors.learning_path.0', 'Only never-published learning path drafts can be deleted.');

    $this->assertDatabaseHas('learning_paths', ['id' => $path->id]);
});

it('rejects archive access without the learning path update permission', function (): void {
    $path = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $this->unauthorized_user();

    $this->postJson(route('api.v1.admin.learning-paths.archive', $path))
        ->assertForbidden();
});

it('updates a draft and synchronizes its ordered steps and media', function (): void {
    Storage::fake('public');
    $oldMedia = MediaUploader::fromSource(UploadedFile::fake()->image('old-cover.jpg'))
        ->toDisk('public')
        ->upload();
    $newMedia = MediaUploader::fromSource(UploadedFile::fake()->image('new-cover.jpg'))
        ->toDisk('public')
        ->upload();
    $oldCourse = Course::factory()->create();
    $newCourse = Course::factory()->create();
    $path      = LearningPath::factory()->create();
    $path->attachMedia($oldMedia, 'cover');
    $path->steps()->create([
        ...learningPathStep(1, 'course', $oldCourse->id, 'Old course'),
    ]);

    $this->authorized_user([PermissionEnum::LEARNING_PATH_UPDATE->value]);

    $this->putJson(
        route('api.v1.admin.learning-paths.update', $path),
        learningPathPayload([
            'title' => 'Updated Backend Path',
            'slug'  => 'updated-backend-path',
            'media' => [
                'cover'   => [$newMedia->id],
                'gallery' => [$oldMedia->id],
            ],
            'steps' => [learningPathStep(1, 'course', $newCourse->id, 'New course')],
        ]),
    )->assertOk();

    $path->refresh();
    expect($path->title)->toBe('Updated Backend Path')
        ->and($path->slug)->toBe('updated-backend-path')
        ->and($path->thumbnail_url)->toBe($newMedia->getUrl())
        ->and($path->steps)->toHaveCount(1)
        ->and($path->steps->first()->productable_id)->toBe($newCourse->id);
    assertDatabaseHas('mediables', [
        'media_id'      => $newMedia->id,
        'mediable_id'   => $path->id,
        'mediable_type' => 'learning_path',
        'tag'           => 'cover',
    ]);
    $this->assertDatabaseMissing('mediables', [
        'media_id'      => $oldMedia->id,
        'mediable_id'   => $path->id,
        'mediable_type' => 'learning_path',
        'tag'           => 'cover',
    ]);
});

it('lists, shows, and deletes a draft', function (): void {
    $path = LearningPath::factory()->create();

    $this->authorized_user([
        PermissionEnum::LEARNING_PATH_VIEW_ANY->value,
        PermissionEnum::LEARNING_PATH_VIEW->value,
    ]);

    $this->getJson(route('api.v1.admin.learning-paths.index'))
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $path->id);
    $this->getJson(route('api.v1.admin.learning-paths.show', $path))
        ->assertOk()
        ->assertJsonPath('data.id', $path->id)
        ->assertJsonPath('data.steps', []);

    $this->authorized_user([PermissionEnum::LEARNING_PATH_DELETE->value]);
    $this->deleteJson(route('api.v1.admin.learning-paths.destroy', $path))
        ->assertNoContent();

    $this->assertDatabaseMissing('learning_paths', ['id' => $path->id]);
});

it('lists learning paths by latest update by default with a stable tie-breaker', function (): void {
    $olderPath = LearningPath::factory()->create(['updated_at' => now()->subDay()]);
    $timestamp = now();
    $firstPath = LearningPath::factory()->create(['updated_at' => $timestamp]);
    $lastPath  = LearningPath::factory()->create(['updated_at' => $timestamp]);

    $this->authorized_user([PermissionEnum::LEARNING_PATH_VIEW_ANY->value]);

    $this->getJson(route('api.v1.admin.learning-paths.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [['created_at', 'updated_at']],
            ],
        ])
        ->assertJsonPath('data.data.0.id', $lastPath->id)
        ->assertJsonPath('data.data.1.id', $firstPath->id)
        ->assertJsonPath('data.data.2.id', $olderPath->id);
});

it('does not allow a non-draft path to return to a deletable draft', function (): void {
    $path = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    $this->authorized_user([
        PermissionEnum::LEARNING_PATH_UPDATE->value,
        PermissionEnum::LEARNING_PATH_DELETE->value,
    ]);

    $this->putJson(
        route('api.v1.admin.learning-paths.update', $path),
        learningPathPayload(['status' => PublicationStatusEnum::DRAFT->value]),
    )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status'])
        ->assertJsonPath('errors.status.0', 'A non-draft learning path cannot return to draft status.');

    $this->deleteJson(route('api.v1.admin.learning-paths.destroy', $path))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['learning_path'])
        ->assertJsonPath('errors.learning_path.0', 'Only never-published learning path drafts can be deleted.');

    expect($path->refresh()->status)->toBe(PublicationStatusEnum::PUBLISHED);
});

it('rejects invalid path content and duplicate references', function (): void {
    $course = Course::factory()->create();
    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);

    $this->postJson(route('api.v1.admin.learning-paths.store'), learningPathPayload([
        'introduction_title'       => null,
        'introduction_description' => null,
        'conclusion_title'         => null,
        'conclusion_description'   => null,
        'steps'                    => [
            learningPathStep(1, 'course', $course->id),
            learningPathStep(1, 'course', $course->id, 'Duplicate course'),
        ],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'introduction_title',
            'introduction_description',
            'conclusion_title',
            'conclusion_description',
            'steps',
        ]);
});

it('rejects productable types outside the learning path allowlist', function (): void {
    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);

    $this->postJson(route('api.v1.admin.learning-paths.store'), learningPathPayload([
        'steps' => [learningPathStep(1, 'bundle', 1)],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['steps.0.productable_type', 'steps.0.productable_id']);
});

it('allows a productable reference to be reused by another path', function (): void {
    $course = Course::factory()->create();
    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);

    $this->postJson(
        route('api.v1.admin.learning-paths.store'),
        learningPathPayload(['steps' => [learningPathStep(1, 'course', $course->id)]]),
    )->assertCreated();
    $this->postJson(
        route('api.v1.admin.learning-paths.store'),
        learningPathPayload([
            'title' => 'A second path',
            'slug'  => 'a-second-path',
            'steps' => [learningPathStep(1, 'course', $course->id)],
        ]),
    )->assertCreated();

    expect(LearningPath::query()->whereHas('steps', function ($query) use ($course): void {
        $query->where('productable_type', 'course')->where('productable_id', $course->id);
    })->count())->toBe(2);
});

it('returns affected learning paths when deleting referenced productables', function (): void {
    $course       = Course::factory()->create();
    $seminar      = Seminar::factory()->create();
    $digitalAsset = DigitalAsset::factory()->create();
    $paths        = [
        LearningPath::factory()->create([
            'title'  => 'Draft Learning Path',
            'slug'   => 'draft-learning-path',
            'status' => PublicationStatusEnum::DRAFT,
        ]),
        LearningPath::factory()->create([
            'title'  => 'Published Learning Path',
            'slug'   => 'published-learning-path',
            'status' => PublicationStatusEnum::PUBLISHED,
        ]),
        LearningPath::factory()->create([
            'title'  => 'Archived Learning Path',
            'slug'   => 'archived-learning-path',
            'status' => PublicationStatusEnum::ARCHIVED,
        ]),
    ];
    foreach ($paths as $path) {
        $path->steps()->createMany([
            learningPathStep(1, 'course', $course->id),
            learningPathStep(2, 'seminar', $seminar->id),
            learningPathStep(3, 'digital_asset', $digitalAsset->id),
        ]);
    }

    $this->authorized_user([
        PermissionEnum::COURSE_DELETE->value,
        PermissionEnum::SEMINAR_DELETE->value,
        PermissionEnum::FILE_DELETE->value,
    ]);

    $expectedPaths = [
        [
            'id'     => $paths[0]->id,
            'title'  => 'Draft Learning Path',
            'slug'   => 'draft-learning-path',
            'status' => PublicationStatusEnum::DRAFT->value,
        ],
        [
            'id'     => $paths[1]->id,
            'title'  => 'Published Learning Path',
            'slug'   => 'published-learning-path',
            'status' => PublicationStatusEnum::PUBLISHED->value,
        ],
        [
            'id'     => $paths[2]->id,
            'title'  => 'Archived Learning Path',
            'slug'   => 'archived-learning-path',
            'status' => PublicationStatusEnum::ARCHIVED->value,
        ],
    ];

    $this->deleteJson(route('api.v1.admin.courses.destroy', $course))
        ->assertUnprocessable()
        ->assertJsonPath('errors.learning_paths', $expectedPaths);
    $this->deleteJson(route('api.v1.admin.seminars.destroy', $seminar))
        ->assertUnprocessable()
        ->assertJsonPath('errors.learning_paths', $expectedPaths);
    $this->deleteJson(route('api.v1.admin.digital-assets.destroy', $digitalAsset))
        ->assertUnprocessable()
        ->assertJsonPath('errors.learning_paths', $expectedPaths);

    $this->assertDatabaseHas('courses', ['id' => $course->id]);
    $this->assertDatabaseHas('seminars', ['id' => $seminar->id]);
    $this->assertDatabaseHas('digital_assets', ['id' => $digitalAsset->id]);
});

it('deletes unreferenced productables through their existing endpoints', function (): void {
    $course       = Course::factory()->create();
    $seminar      = Seminar::factory()->create();
    $digitalAsset = DigitalAsset::factory()->create();

    $this->authorized_user([
        PermissionEnum::COURSE_DELETE->value,
        PermissionEnum::SEMINAR_DELETE->value,
        PermissionEnum::FILE_DELETE->value,
    ]);

    $this->deleteJson(route('api.v1.admin.courses.destroy', $course))->assertNoContent();
    $this->deleteJson(route('api.v1.admin.seminars.destroy', $seminar))->assertNoContent();
    $this->deleteJson(route('api.v1.admin.digital-assets.destroy', $digitalAsset))->assertNoContent();

    $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    $this->assertDatabaseMissing('seminars', ['id' => $seminar->id]);
    $this->assertDatabaseMissing('digital_assets', ['id' => $digitalAsset->id]);
});

it('rejects staff without the dedicated permission', function (): void {
    $path = LearningPath::factory()->create();
    $this->unauthorized_user();

    $this->getJson(route('api.v1.admin.learning-paths.index'))->assertForbidden();
    $this->getJson(route('api.v1.admin.learning-paths.show', $path))->assertForbidden();
    $this->postJson(route('api.v1.admin.learning-paths.store'), learningPathPayload())
        ->assertForbidden();
    $this->putJson(route('api.v1.admin.learning-paths.update', $path), learningPathPayload())
        ->assertForbidden();
    $this->deleteJson(route('api.v1.admin.learning-paths.destroy', $path))->assertForbidden();
});
