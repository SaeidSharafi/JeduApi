<?php

declare(strict_types=1);

use App\Actions\Admin\LearningPath\ArchiveLearningPathAction;
use App\Actions\Admin\LearningPath\CreateLearningPathAction;
use App\Actions\Admin\LearningPath\DeleteLearningPathAction;
use App\Actions\Admin\LearningPath\EnsureProductableHasNoLearningPathReferencesAction;
use App\Actions\Admin\LearningPath\SyncLearningPathStepsAction;
use App\Actions\Admin\LearningPath\UpdateLearningPathAction;
use App\Data\Admin\LearningPath\LearningPathCreateData;
use App\Data\Admin\LearningPath\LearningPathUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\System\MorphTypeEnum;
use App\Exceptions\ModelHasRelationshipDataException;
use App\Models\Course;
use App\Models\LearningPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Plank\Mediable\Facades\MediaUploader;
use Plank\Mediable\Media;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

covers(
    ArchiveLearningPathAction::class,
    CreateLearningPathAction::class,
    DeleteLearningPathAction::class,
    EnsureProductableHasNoLearningPathReferencesAction::class,
    SyncLearningPathStepsAction::class,
    UpdateLearningPathAction::class,
);

/**
 * @return array{cover: Media, gallery: Media}
 */
function learningPathActionMedia(): array
{
    return [
        'cover' => MediaUploader::fromSource(UploadedFile::fake()->image('learning-path-cover.jpg'))
            ->toDisk('public')
            ->upload(),
        'gallery' => MediaUploader::fromSource(UploadedFile::fake()->image('learning-path-gallery.jpg'))
            ->toDisk('public')
            ->upload(),
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $steps
 * @param  array{cover: Media, gallery: Media}  $media
 */
function learningPathCreateData(array $steps, array $media, string $status = 'draft'): LearningPathCreateData
{
    return new LearningPathCreateData(
        title: 'Backend Engineering Path',
        slug: 'backend-engineering-path',
        description: 'A guided path for backend engineering.',
        introduction_title: 'Start here',
        introduction_description: 'Begin with the foundations.',
        conclusion_title: 'Keep building',
        conclusion_description: 'Apply what you learned.',
        status: $status,
        steps: $steps,
        media: [
            'cover'   => [$media['cover']->id],
            'gallery' => [$media['gallery']->id],
        ],
    );
}

/**
 * @param  array<int, array<string, mixed>>  $steps
 * @param  array{cover: Media, gallery: Media}  $media
 */
function learningPathUpdateData(array $steps, array $media, string $status = 'draft'): LearningPathUpdateData
{
    return new LearningPathUpdateData(
        title: 'Updated Backend Engineering Path',
        slug: 'updated-backend-engineering-path',
        description: 'An updated guided path for backend engineering.',
        introduction_title: 'Start here again',
        introduction_description: 'Begin with the updated foundations.',
        conclusion_title: 'Keep building',
        conclusion_description: 'Apply the updated material.',
        status: $status,
        steps: $steps,
        media: [
            'cover'   => [$media['cover']->id],
            'gallery' => [$media['gallery']->id],
        ],
    );
}

function learningPathStatusUpdateData(string $status): LearningPathUpdateData
{
    return new LearningPathUpdateData(
        title: 'Learning Path',
        slug: 'learning-path',
        description: 'Learning path description.',
        introduction_title: 'Introduction',
        introduction_description: 'Introduction description.',
        conclusion_title: 'Conclusion',
        conclusion_description: 'Conclusion description.',
        status: $status,
    );
}

it('creates a learning path with its thumbnail, media groups, and ordered steps', function (): void {
    Storage::fake('public');
    $media        = learningPathActionMedia();
    $course       = Course::factory()->create();
    $secondCourse = Course::factory()->create();

    $learningPath = app(CreateLearningPathAction::class)->handle(learningPathCreateData([
        [
            'position'         => 2,
            'productable_type' => 'course',
            'productable_id'   => $secondCourse->id,
            'title'            => 'Build the course project',
            'description'      => 'Use the concepts in a project.',
        ],
        [
            'position'         => 1,
            'productable_type' => 'course',
            'productable_id'   => $course->id,
            'title'            => 'Learn the course',
            'description'      => 'Build the required foundation.',
        ],
    ], $media));

    expect($learningPath->thumbnail_url)->toBe($media['cover']->getUrl())
        ->and($learningPath->fresh()->steps->pluck('position')->all())->toBe([1, 2]);

    assertDatabaseHas('mediables', [
        'media_id'      => $media['cover']->id,
        'mediable_id'   => $learningPath->id,
        'mediable_type' => 'learning_path',
        'tag'           => 'cover',
    ]);
    assertDatabaseHas('mediables', [
        'media_id'      => $media['gallery']->id,
        'mediable_id'   => $learningPath->id,
        'mediable_type' => 'learning_path',
        'tag'           => 'gallery',
    ]);
});

it('updates the thumbnail, media groups, and step ordering together', function (): void {
    Storage::fake('public');
    $oldMedia  = learningPathActionMedia();
    $newMedia  = learningPathActionMedia();
    $oldCourse = Course::factory()->create();
    $newCourse = Course::factory()->create();
    $path      = LearningPath::factory()->create();
    $path->attachMedia($oldMedia['cover'], 'cover');
    $path->attachMedia($oldMedia['gallery'], 'gallery');
    $path->steps()->create([
        'position'         => 1,
        'productable_type' => 'course',
        'productable_id'   => $oldCourse->id,
        'title'            => 'Old step',
        'description'      => 'Old description',
    ]);

    $updatedPath = app(UpdateLearningPathAction::class)->handle(
        learningPathUpdateData([
            [
                'position'         => 2,
                'productable_type' => 'course',
                'productable_id'   => $oldCourse->id,
                'title'            => 'Second step',
                'description'      => 'Second description.',
            ],
            [
                'position'         => 1,
                'productable_type' => 'course',
                'productable_id'   => $newCourse->id,
                'title'            => 'First step',
                'description'      => 'First description.',
            ],
        ], $newMedia),
        $path,
    );

    expect($updatedPath->thumbnail_url)->toBe($newMedia['cover']->getUrl())
        ->and($updatedPath->steps->pluck('title')->all())->toBe(['First step', 'Second step']);

    assertDatabaseMissing('mediables', [
        'media_id'      => $oldMedia['cover']->id,
        'mediable_id'   => $path->id,
        'mediable_type' => 'learning_path',
        'tag'           => 'cover',
    ]);
    assertDatabaseHas('mediables', [
        'media_id'      => $newMedia['cover']->id,
        'mediable_id'   => $path->id,
        'mediable_type' => 'learning_path',
        'tag'           => 'cover',
    ]);
    expect(DB::table('learning_path_steps')->where('learning_path_id', $path->id)->count())->toBe(2);
});

it('archives published paths and rejects invalid archive transitions', function (): void {
    $published = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    $archived = app(ArchiveLearningPathAction::class)->handle($published);

    expect($archived->status)->toBe(PublicationStatusEnum::ARCHIVED);

    $draft = LearningPath::factory()->create(['status' => PublicationStatusEnum::DRAFT]);

    try {
        app(ArchiveLearningPathAction::class)->handle($draft);
        expect(false)->toBeTrue();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'status' => ['Only published learning paths can be archived.'],
        ]);
    }
});

it('enforces learning path lifecycle transitions during updates', function (): void {
    $action      = app(UpdateLearningPathAction::class);
    $transitions = [
        [
            'current' => PublicationStatusEnum::PUBLISHED,
            'next'    => PublicationStatusEnum::DRAFT,
            'message' => 'A non-draft learning path cannot return to draft status.',
        ],
        [
            'current' => PublicationStatusEnum::DRAFT,
            'next'    => PublicationStatusEnum::ARCHIVED,
            'message' => 'Only published learning paths can be archived.',
        ],
        [
            'current' => PublicationStatusEnum::PUBLISHED,
            'next'    => PublicationStatusEnum::ARCHIVED,
            'message' => 'Use the archive operation to retire a published learning path.',
        ],
        [
            'current' => PublicationStatusEnum::ARCHIVED,
            'next'    => PublicationStatusEnum::PUBLISHED,
            'message' => 'An archived learning path cannot return to another lifecycle state.',
        ],
    ];

    foreach ($transitions as $transition) {
        $path = LearningPath::factory()->create(['status' => $transition['current']]);

        try {
            $action->handle(learningPathStatusUpdateData($transition['next']->value), $path);
            expect(false)->toBeTrue();
        } catch (ValidationException $exception) {
            expect($exception->errors())->toBe(['status' => [$transition['message']]]);
        }
    }
});

it('allows a published path to remain published during an update', function (): void {
    Storage::fake('public');
    $media = learningPathActionMedia();
    $path  = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    $updatedPath = app(UpdateLearningPathAction::class)->handle(
        learningPathUpdateData([], $media, PublicationStatusEnum::PUBLISHED->value),
        $path,
    );

    expect($updatedPath->status)->toBe(PublicationStatusEnum::PUBLISHED)
        ->and($updatedPath->thumbnail_url)->toBe($media['cover']->getUrl());
});

it('replaces and sorts learning path steps when syncing directly', function (): void {
    $path         = LearningPath::factory()->create();
    $firstCourse  = Course::factory()->create();
    $secondCourse = Course::factory()->create();
    $path->steps()->create([
        'position'         => 1,
        'productable_type' => 'course',
        'productable_id'   => $firstCourse->id,
        'title'            => 'Old step',
        'description'      => 'Old description.',
    ]);

    app(SyncLearningPathStepsAction::class)->handle($path, [
        [
            'position'         => 2,
            'productable_type' => 'course',
            'productable_id'   => $secondCourse->id,
            'title'            => 'Second step',
            'description'      => 'Second description.',
        ],
        [
            'position'         => 1,
            'productable_type' => 'course',
            'productable_id'   => $firstCourse->id,
            'title'            => 'First step',
            'description'      => 'First description.',
        ],
    ]);

    expect($path->fresh()->steps->pluck('title')->all())->toBe(['First step', 'Second step']);
});

it('deletes a draft path and detaches its media', function (): void {
    Storage::fake('public');
    $media = learningPathActionMedia();
    $path  = LearningPath::factory()->create(['status' => PublicationStatusEnum::DRAFT]);
    $path->attachMedia($media['cover'], 'cover');
    $path->attachMedia($media['gallery'], 'gallery');

    app(DeleteLearningPathAction::class)->handle($path);

    expect(LearningPath::query()->whereKey($path->id)->exists())->toBeFalse();
    expect(DB::table('mediables')
        ->where('mediable_id', $path->id)
        ->where('mediable_type', 'learning_path')
        ->exists())->toBeFalse();
});

it('rejects deleting a path that is no longer a draft', function (): void {
    $path = LearningPath::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);

    try {
        app(DeleteLearningPathAction::class)->handle($path);
        expect(false)->toBeTrue();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'learning_path' => ['Only never-published learning path drafts can be deleted.'],
        ]);
    }
});

it('rejects deletion references and allows unreferenced productables', function (): void {
    $course = Course::factory()->create();
    $action = app(EnsureProductableHasNoLearningPathReferencesAction::class);

    $action->handle(MorphTypeEnum::COURSE, $course->id);

    $path = LearningPath::factory()->create([
        'title'  => 'Referenced path',
        'slug'   => 'referenced-path',
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);
    $path->steps()->create([
        'position'         => 1,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'productable_id'   => $course->id,
        'title'            => 'Referenced course',
        'description'      => 'This course belongs to the path.',
    ]);
    $otherCourse = Course::factory()->create();
    $otherPath   = LearningPath::factory()->create([
        'title'  => 'Other path',
        'slug'   => 'other-path',
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);
    $otherPath->steps()->create([
        'position'         => 1,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'productable_id'   => $otherCourse->id,
        'title'            => 'Other course',
        'description'      => 'This course belongs to another path.',
    ]);

    try {
        $action->handle(MorphTypeEnum::COURSE, $course->id);
        expect(false)->toBeTrue();
    } catch (ModelHasRelationshipDataException $exception) {
        expect($exception->getRelatedModel())->toBe(LearningPath::class)
            ->and($exception->getErrors())->toBe([
                'learning_paths' => [[
                    'id'     => $path->id,
                    'title'  => 'Referenced path',
                    'slug'   => 'referenced-path',
                    'status' => PublicationStatusEnum::PUBLISHED->value,
                ]],
            ]);
    }
});
