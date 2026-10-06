<?php

declare(strict_types=1);

use App\Console\Commands\ImportExport\CleanupImportExportArtifactsCommand;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\ImportExportArtifact;
use App\Models\ImportRun;
use App\Models\User;
use App\Models\UserProviderAccount;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

mutates(CleanupImportExportArtifactsCommand::class);

beforeEach(function (): void {
    Storage::fake('local');
});

it('expires inventoried exports and leaves untracked files untouched', function (): void {
    $disk          = Storage::disk('local');
    $uuid          = (string) Str::uuid();
    $expiredPath   = "exports/users/{$uuid}.xlsx";
    $unrelatedPath = 'exports/users/notes.txt';
    $publicPath    = 'exports/users/'.$uuid.'.xlsx';
    $disk->put($expiredPath, 'private export');
    $disk->put($unrelatedPath, 'unrelated private file');
    ImportExportArtifact::factory()->create([
        'resource'      => 'users',
        'artifact_uuid' => $uuid,
        'path'          => $expiredPath,
        'expires_at'    => now()->subSecond(),
    ]);
    Storage::fake('public');
    Storage::disk('public')->put($publicPath, 'public artifact');

    $this->artisan('imports:cleanup-artifacts')->assertSuccessful();

    expect($disk->exists($expiredPath))->toBeFalse()
        ->and($disk->exists($unrelatedPath))->toBeTrue()
        ->and(Storage::disk('public')->exists($publicPath))->toBeTrue()
        ->and(ImportExportArtifact::query()->where('artifact_uuid', $uuid)->exists())->toBeFalse();
});

it('keeps expired run references and snapshots when storage deletion fails', function (): void {
    $run        = ImportRun::factory()->create(['artifacts_expires_at' => now()->subSecond()]);
    $laterRun   = ImportRun::factory()->create(['artifacts_expires_at' => now()->subSecond(), 'file_path' => null]);
    $uploadPath = "imports/{$run->uuid}/upload.xlsx";
    $run->update(['file_path' => $uploadPath]);
    $row = $run->rows()->create([
        'row_number'     => 2,
        'identity_value' => 'PRIVATE-IDENTITY-1234',
        'is_valid'       => true,
        'data'           => ['phone' => 'PRIVATE-IDENTITY-1234'],
    ]);
    $disk = Storage::disk('local');
    $disk->put($uploadPath, 'private import');
    $storageMock = Mockery::mock(FilesystemAdapter::class);
    $storageMock->shouldReceive('path')->with('exports/users')->andReturn($disk->path('exports/users'));
    $storageMock->shouldReceive('exists')->with($uploadPath)->andReturnTrue();
    $storageMock->shouldReceive('delete')->with($uploadPath)->andReturnFalse();
    Storage::shouldReceive('disk')->with('local')->andReturn($storageMock);

    $this->artisan('imports:cleanup-artifacts', ['--batch' => 1])->assertSuccessful();

    expect($run->fresh()->file_path)->toBe($uploadPath)
        ->and($run->fresh()->artifacts_cleanup_retry_after)->not->toBeNull()
        ->and($row->fresh()->identity_value)->toBe('PRIVATE-IDENTITY-1234')
        ->and($row->fresh()->data)->toBe(['phone' => 'PRIVATE-IDENTITY-1234']);

    $this->artisan('imports:cleanup-artifacts', ['--batch' => 1])->assertSuccessful();

    expect($laterRun->fresh()->artifacts_redacted_at)->not->toBeNull();
});

it('defers failed export deletion while processing later expired exports', function (): void {
    $firstUuid  = (string) Str::uuid();
    $secondUuid = (string) Str::uuid();
    $firstPath  = "exports/users/{$firstUuid}.xlsx";
    $secondPath = "exports/users/{$secondUuid}.xlsx";
    ImportExportArtifact::factory()->create([
        'resource'      => 'users',
        'artifact_uuid' => $firstUuid,
        'path'          => $firstPath,
        'expires_at'    => now()->subSecond(),
    ]);
    $second = ImportExportArtifact::factory()->create([
        'resource'      => 'users',
        'artifact_uuid' => $secondUuid,
        'path'          => $secondPath,
        'expires_at'    => now()->subSecond(),
    ]);
    $disk = Storage::disk('local');
    $disk->put($firstPath, 'first private export');
    $disk->put($secondPath, 'second private export');
    $storageMock = Mockery::mock(FilesystemAdapter::class);
    $storageMock->shouldReceive('exists')->with($firstPath)->once()->andReturnTrue();
    $storageMock->shouldReceive('delete')->with($firstPath)->once()->andReturnFalse();
    $storageMock->shouldReceive('exists')->with($secondPath)->once()->andReturnTrue();
    $storageMock->shouldReceive('delete')->with($secondPath)->once()->andReturnTrue();
    Storage::shouldReceive('disk')->with('local')->andReturn($storageMock);

    $this->artisan('imports:cleanup-artifacts', ['--batch' => 1])->assertSuccessful();
    expect(ImportExportArtifact::query()->where('artifact_uuid', $firstUuid)->firstOrFail()->cleanup_retry_after)->not->toBeNull();

    $this->artisan('imports:cleanup-artifacts', ['--batch' => 1])->assertSuccessful();

    expect(ImportExportArtifact::query()->where('artifact_uuid', $secondUuid)->exists())->toBeFalse()
        ->and(ImportExportArtifact::query()->where('artifact_uuid', $firstUuid)->exists())->toBeTrue();
});

it('continues through later expired runs across bounded cleanup batches', function (): void {
    $first  = ImportRun::factory()->create(['artifacts_expires_at' => now()->subSecond(), 'file_path' => null]);
    $second = ImportRun::factory()->create(['artifacts_expires_at' => now()->subSecond(), 'file_path' => null]);

    $this->artisan('imports:cleanup-artifacts', ['--batch' => 1])->assertSuccessful();

    expect($first->fresh()->artifacts_redacted_at)->not->toBeNull()
        ->and($second->fresh()->artifacts_redacted_at)->toBeNull();

    $this->artisan('imports:cleanup-artifacts', ['--batch' => 1])->assertSuccessful();

    expect($second->fresh()->artifacts_redacted_at)->not->toBeNull();
});

it('defers expired files and snapshots for active provider work', function (): void {
    $run = ImportRun::factory()->create([
        'status'               => ImportRunStatusEnum::PROCESSING,
        'artifacts_expires_at' => now()->subSecond(),
    ]);
    $uploadPath = "imports/{$run->uuid}/upload.xlsx";
    $run->update(['file_path' => $uploadPath]);
    $row = $run->rows()->create([
        'row_number'     => 2,
        'identity_value' => 'PRIVATE-IDENTITY-1234',
        'is_valid'       => true,
        'data'           => ['phone' => 'PRIVATE-IDENTITY-1234'],
        'providers'      => ['moodle' => ['status' => 'processing', 'message' => null, 'attempts' => 1]],
    ]);
    Storage::disk('local')->put($uploadPath, 'private import');

    $this->artisan('imports:cleanup-artifacts')->assertSuccessful();

    expect($run->fresh()->status)->toBe(ImportRunStatusEnum::PROCESSING)
        ->and(Storage::disk('local')->exists($uploadPath))->toBeTrue()
        ->and($row->fresh()->identity_value)->toBe('PRIVATE-IDENTITY-1234');
});

it('deletes expired files and redacts snapshots while preserving safe run history', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE, PermissionEnum::IMPORT_RESULTS]);
    $runId = postImportPreview($this, userImportFile([
        userImportRow(['phone' => 'PRIVATE-PHONE-9977', 'first_name' => null]),
        userImportRow(['phone' => '09123456789']),
    ]))->json('data.run_id');
    $run        = ImportRun::query()->where('uuid', $runId)->firstOrFail();
    $uploadPath = $run->file_path;
    postImportApproval($this, $runId, ['include_valid_rows' => true])->assertSuccessful();
    $run->refresh();
    $run->update(['status' => ImportRunStatusEnum::COMPLETED_WITH_PROVIDER_FAILURES, 'provider_queued_count' => 1]);
    $successfulRow = $run->rows()->where('is_valid', true)->firstOrFail();
    $user          = User::query()->where('phone', '09123456789')->firstOrFail();
    $successfulRow->update([
        'providers'         => ['moodle' => ['status' => 'failed', 'message' => 'PRIVATE-PROVIDER-DETAIL', 'attempts' => 2]],
        'local_result_data' => ['id' => $user->id, 'phone' => 'PRIVATE-RESULT-VALUE'],
    ]);
    UserProviderAccount::factory()->for($user)->create(['provider' => 'moodle']);
    $row = $run->rows()->where('is_valid', false)->firstOrFail();
    $this->travelTo($run->artifacts_expires_at);

    $this->artisan('imports:cleanup-artifacts')->assertSuccessful();

    $run->refresh();
    $row->refresh();
    $successfulRow->refresh();
    $poll = $this->getJson('/api/v1/admin/users/import/'.$runId)
        ->assertSuccessful()
        ->assertJsonPath('data.status', ImportRunStatusEnum::COMPLETED_WITH_PROVIDER_FAILURES->value)
        ->assertJsonPath('data.summary.provider_failure_count', 1)
        ->assertJsonPath('data.rows.1.providers.moodle.status', 'failed')
        ->assertJsonPath('data.rows.1.providers.moodle.message', null)
        ->assertJsonPath('data.rows.1.data', [])
        ->assertJsonPath('data.error_report.available', false);
    expect($run->rows_total)->toBe(2)
        ->and($run->rows_invalid)->toBe(1)
        ->and($run->created_count)->toBe(1)
        ->and($run->approved_at)->not->toBeNull()
        ->and(User::query()->where('phone', '09123456789')->exists())->toBeTrue()
        ->and(UserProviderAccount::query()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and($successfulRow->local_result_data)->toBeNull()
        ->and($successfulRow->providers['moodle'])->toMatchArray(['status' => 'failed', 'message' => null, 'attempts' => 2])
        ->and($run->original_filename)->toBe('')
        ->and($run->file_path)->toBeNull()
        ->and($row->row_number)->toBe(2)
        ->and($row->is_valid)->toBeFalse()
        ->and($row->identity_value)->toBeNull()
        ->and($row->data)->toBeNull()
        ->and($row->errors)->toBeNull()
        ->and(Storage::disk('local')->exists($uploadPath))->toBeFalse()
        ->and(json_encode($poll->json()))->not->toContain('PRIVATE-PROVIDER-DETAIL')->not->toContain('PRIVATE-RESULT-VALUE');
});
