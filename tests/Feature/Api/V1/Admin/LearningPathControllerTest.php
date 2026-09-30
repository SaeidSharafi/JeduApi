<?php

declare(strict_types=1);

use App\Actions\Admin\Course\DeleteCourseAction;
use App\Actions\Admin\DigitalAsset\DeleteDigitalAssetAction;
use App\Actions\Admin\LearningPath\CreateLearningPathAction;
use App\Actions\Admin\LearningPath\DeleteLearningPathAction;
use App\Actions\Admin\LearningPath\SyncLearningPathStepsAction;
use App\Actions\Admin\LearningPath\UpdateLearningPathAction;
use App\Actions\Admin\Seminar\DeleteSeminarAction;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Api\Admin\LearningPathController;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\LearningPath;
use App\Models\Seminar;
use App\Rules\LearningPathProductableExistRule;
use App\Rules\LearningPathStepsRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;

use function Pest\Laravel\assertDatabaseHas;

covers(
    LearningPathController::class,
    CreateLearningPathAction::class,
    DeleteLearningPathAction::class,
    SyncLearningPathStepsAction::class,
    UpdateLearningPathAction::class,
    DeleteCourseAction::class,
    DeleteSeminarAction::class,
    DeleteDigitalAssetAction::class,
    LearningPathProductableExistRule::class,
    LearningPathStepsRule::class,
);

uses(Tests\Support\Traits\AuthTestTrait::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function learningPathPayload(array $overrides = []): array
{
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
            'cover'       => [],
            'gallery'     => [],
            'video'       => [],
            'certificate' => [],
            'main'        => [],
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
    $course       = Course::factory()->create();
    $seminar      = Seminar::factory()->create();
    $digitalAsset = DigitalAsset::factory()->create();
    $products     = DB::table('products')->count();
    $options      = DB::table('product_delivery_options')->count();

    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);

    $response = $this->postJson(route('api.v1.admin.learning-paths.store'), learningPathPayload([
        'media' => [
            'cover'       => [$cover->id],
            'gallery'     => [],
            'video'       => [],
            'certificate' => [],
            'main'        => [],
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

it('rejects a published path when steps are omitted', function (): void {
    $this->authorized_user([PermissionEnum::LEARNING_PATH_CREATE->value]);
    $payload = learningPathPayload(['status' => 'published']);
    unset($payload['steps']);

    $this->postJson(route('api.v1.admin.learning-paths.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['steps']);
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
                'cover'       => [$newMedia->id],
                'gallery'     => [],
                'video'       => [],
                'certificate' => [],
                'main'        => [],
            ],
            'steps' => [learningPathStep(1, 'course', $newCourse->id, 'New course')],
        ]),
    )->assertOk();

    $path->refresh();
    expect($path->title)->toBe('Updated Backend Path')
        ->and($path->slug)->toBe('updated-backend-path')
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

it('preserves existing media when an update omits media', function (): void {
    Storage::fake('public');
    $media = MediaUploader::fromSource(UploadedFile::fake()->image('preserved-cover.jpg'))
        ->toDisk('public')
        ->upload();
    $path = LearningPath::factory()->create();
    $path->attachMedia($media, 'cover');
    $payload = learningPathPayload(['slug' => 'media-preserving-path']);
    unset($payload['media']);

    $this->authorized_user([PermissionEnum::LEARNING_PATH_UPDATE->value]);

    $this->putJson(route('api.v1.admin.learning-paths.update', $path), $payload)
        ->assertOk();

    assertDatabaseHas('mediables', [
        'media_id'      => $media->id,
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

it('lists learning paths by latest update by default', function (): void {
    $olderPath  = LearningPath::factory()->create(['updated_at' => now()->subDay()]);
    $latestPath = LearningPath::factory()->create();

    $this->authorized_user([PermissionEnum::LEARNING_PATH_VIEW_ANY->value]);

    $this->getJson(route('api.v1.admin.learning-paths.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [['created_at', 'updated_at']],
            ],
        ])
        ->assertJsonPath('data.data.0.id', $latestPath->id)
        ->assertJsonPath('data.data.1.id', $olderPath->id);
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
        ->assertJsonValidationErrors(['status']);

    $this->deleteJson(route('api.v1.admin.learning-paths.destroy', $path))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['learning_path']);

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

it('prevents deleting productables referenced by a learning path', function (): void {
    $course       = Course::factory()->create();
    $seminar      = Seminar::factory()->create();
    $digitalAsset = DigitalAsset::factory()->create();
    $path         = LearningPath::factory()->create();
    $path->steps()->createMany([
        learningPathStep(1, 'course', $course->id),
        learningPathStep(2, 'seminar', $seminar->id),
        learningPathStep(3, 'digital_asset', $digitalAsset->id),
    ]);

    $this->authorized_user([
        PermissionEnum::COURSE_DELETE->value,
        PermissionEnum::SEMINAR_DELETE->value,
        PermissionEnum::FILE_DELETE->value,
    ]);

    $this->deleteJson(route('api.v1.admin.courses.destroy', $course))
        ->assertUnprocessable();
    $this->deleteJson(route('api.v1.admin.seminars.destroy', $seminar))
        ->assertUnprocessable();
    $this->deleteJson(route('api.v1.admin.digital-assets.destroy', $digitalAsset))
        ->assertUnprocessable();

    expect(Course::query()->whereKey($course)->exists())->toBeTrue()
        ->and(Seminar::query()->whereKey($seminar)->exists())->toBeTrue()
        ->and(DigitalAsset::query()->whereKey($digitalAsset)->exists())->toBeTrue();
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
