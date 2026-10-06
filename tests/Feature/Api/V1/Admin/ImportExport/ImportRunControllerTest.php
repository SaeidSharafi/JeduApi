<?php

declare(strict_types=1);

use App\Actions\Admin\ImportExport\GetImportRunAction;
use App\Enums\PermissionEnum;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

covers(GetImportRunAction::class);

it('rejects unauthenticated polling', function (): void {
    $this->getJson('/api/v1/admin/users/import/'.Illuminate\Support\Str::uuid())->assertUnauthorized();
});

it('requires the separate results permission', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_APPROVE]);

    $this->getJson('/api/v1/admin/users/import/'.Illuminate\Support\Str::uuid())->assertForbidden();
});

it('returns 404 for a missing run', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_RESULTS]);

    $this->getJson('/api/v1/admin/users/import/'.Illuminate\Support\Str::uuid())->assertNotFound();
});

it('publishes and reuses a private sanitized validation error workbook', function (): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_RESULTS]);
    $runId = postImportPreview($this, userImportFile([
        userImportRow(['phone' => 'CELL-SECRET-7788', 'first_name' => null]),
    ]))->json('data.run_id');
    $row = App\Models\ImportRun::query()->where('uuid', $runId)->firstOrFail()->rows()->firstOrFail();
    $row->update(['errors' => [[
        'field'   => 'first_name',
        'code'    => 'required',
        'message' => 'CELL-SECRET-7788 leaked in raw validation text',
    ], [
        'field'   => 'phone',
        'code'    => 'invalid',
        'message' => 'CELL-SECRET-7788 leaked in another raw message',
    ]]]);

    $poll = $this->getJson('/api/v1/admin/users/import/'.$runId)->assertSuccessful()
        ->assertJsonPath('data.error_report.available', true)
        ->assertJsonPath('data.error_report.download_url', '/api/v1/admin/users/import/'.$runId.'/errors');
    $reportPath = $row->fresh()->importRun->error_report_path;
    expect($reportPath)->not->toBeNull();

    $download = $this->get('/api/v1/admin/users/import/'.$runId.'/errors');
    $download->assertOk()->assertDownload('users-import-errors.xlsx');
    expect($download->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    $workbookRows = importWorksheetRows(Storage::disk('local')->path($reportPath));
    expect($workbookRows[0])->toBe(['row_number', 'field', 'code', 'message', 'provider'])
        ->and($workbookRows[1][0])->toBe('2')
        ->and($workbookRows[1][1])->toBe('First name')
        ->and($workbookRows[1][2])->toBe('required')
        ->and($workbookRows[1][3])->toBe('A required value is missing.')
        ->and($workbookRows[2][1])->toBe('Mobile phone')
        ->and($workbookRows[2][2])->toBe('invalid')
        ->and(json_encode($workbookRows))->not->toContain('CELL-SECRET-7788')
        ->and($poll->json())->not->toContain('CELL-SECRET-7788');

    $this->getJson('/api/v1/admin/users/import/'.$runId);
    expect($row->fresh()->importRun->error_report_path)->toBe($reportPath);
});

it('rejects unavailable reports and enforces results authorization on downloads', function (): void {
    Storage::fake('local');
    $this->get('/api/v1/admin/users/import/'.Illuminate\Support\Str::uuid().'/errors')->assertUnauthorized();
    $this->get('/api/v1/admin/unknown/import/'.Illuminate\Support\Str::uuid().'/errors')->assertNotFound();
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))->json('data.run_id');

    $this->get('/api/v1/admin/users/import/'.$runId.'/errors')->assertForbidden();
    $this->authorized_user([PermissionEnum::IMPORT_RESULTS]);
    $this->get('/api/v1/admin/users/import/'.$runId.'/errors')->assertNotFound();
});

it('refreshes a validation report with only final failed provider outcomes', function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE, PermissionEnum::IMPORT_RESULTS]);
    $moodle = $this->mock(App\Contracts\Integrations\MoodleClientContract::class);
    $moodle->shouldReceive('isEnabled')->once()->andReturnTrue();
    $moodle->shouldReceive('assertConfigured')->once();
    $moodle->shouldReceive('findOrCreateUser')->once()->andThrow(new App\Exceptions\Integrations\UnrecoverableProvisioningException('provider-secret-991'));
    $niliroom = $this->mock(App\Contracts\Integrations\NiliroomClientContract::class);
    $niliroom->shouldReceive('isEnabled')->once()->andReturnTrue();
    $niliroom->shouldReceive('assertConfigured')->once();
    $niliroom->shouldReceive('ensureUser')->once()->andReturn('provider-id-1');
    $runId = postImportPreview($this, userImportFile([
        userImportRow(['first_name' => null]),
        userImportRow(['phone' => '09123456780', 'provision_moodle' => 'true', 'provision_niliroom' => 'true']),
    ]))->json('data.run_id');
    $this->getJson('/api/v1/admin/users/import/'.$runId)->assertJsonPath('data.error_report.available', true);
    $run       = App\Models\ImportRun::query()->where('uuid', $runId)->firstOrFail();
    $firstPath = $run->error_report_path;

    postImportApproval($this, $runId, ['include_valid_rows' => true])->assertSuccessful();
    $row = $run->rows()->where('row_number', 3)->firstOrFail();
    $this->app->call([new App\Jobs\Provisioning\ProvisionImportUserProviderJob($row->id, App\Enums\ImportExport\UserProvisioningProviderEnum::MOODLE), 'handle']);
    $this->app->call([new App\Jobs\Provisioning\ProvisionImportUserProviderJob($row->id, App\Enums\ImportExport\UserProvisioningProviderEnum::NILIROOM), 'handle']);

    $this->getJson('/api/v1/admin/users/import/'.$runId)->assertSuccessful()
        ->assertJsonPath('data.status', 'completed_with_provider_failures');
    $finalPath = $run->fresh()->error_report_path;
    expect($finalPath)->not->toBe($firstPath);
    $report = importWorksheetRows(Storage::disk('local')->path($finalPath));
    expect(count($report))->toBe(3)
        ->and($report[1][0])->toBe('2')
        ->and($report[2])->toBe(['3', null, 'provider_failed', 'The requested provider operation failed.', 'moodle'])
        ->and(json_encode($report))->not->toContain('provider-secret-991')
        ->and(json_encode($report))->not->toContain('niliroom');
});

it('completes local-only imports and retains the original preview snapshot', function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE, PermissionEnum::IMPORT_RESULTS]);
    $runId       = postImportPreview($this, userImportFile([userImportRow()]))->json('data.run_id');
    $row         = App\Models\ImportRun::query()->where('uuid', $runId)->firstOrFail()->rows()->firstOrFail();
    $previewData = $row->data;

    postImportApproval($this, $runId)->assertSuccessful();

    $user = App\Models\User::query()->where('phone', '09123456789')->firstOrFail();
    $this->getJson('/api/v1/admin/users/import/'.$runId)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.rows.0.data.id', $user->id);
    expect($row->fresh()->data)->toBe($previewData);
    expect($row->fresh()->target_resource_id)->toBeNull();
    Queue::assertNothingPushed();
});

it('polls queued provider outcomes without exposing passwords', function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE, PermissionEnum::IMPORT_RESULTS]);
    $runId = postImportPreview($this, userImportFile([
        userImportRow(['provision_moodle' => 'true', 'password' => 'secret-pass-123']),
    ]))->json('data.run_id');
    postImportApproval($this, $runId)->assertSuccessful();

    $response = $this->getJson('/api/v1/admin/users/import/'.$runId);

    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.rows.0.providers.moodle.status', 'queued')
        ->assertJsonMissingPath('data.rows.0.data.password');
});

it('keeps retryable work processing until a retry succeeds', function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE, PermissionEnum::IMPORT_RESULTS]);
    $client = $this->mock(App\Contracts\Integrations\MoodleClientContract::class);
    $client->shouldReceive('isEnabled')->twice()->andReturnTrue();
    $client->shouldReceive('assertConfigured')->twice();
    $client->shouldReceive('findOrCreateUser')->once()->andThrow(new App\Exceptions\Integrations\RecoverableProvisioningException('temporary outage'));
    $client->shouldReceive('findOrCreateUser')->once()->andReturn([123, '0000000019']);
    $runId = postImportPreview($this, userImportFile([userImportRow(['provision_moodle' => 'true'])]))->json('data.run_id');
    postImportApproval($this, $runId)->assertSuccessful();
    $row = App\Models\ImportRun::query()->where('uuid', $runId)->firstOrFail()->rows()->firstOrFail();
    $job = new App\Jobs\Provisioning\ProvisionImportUserProviderJob($row->id, App\Enums\ImportExport\UserProvisioningProviderEnum::MOODLE);

    expect(fn () => $this->app->call([$job, 'handle']))->toThrow(App\Exceptions\Integrations\RecoverableProvisioningException::class);
    $this->getJson('/api/v1/admin/users/import/'.$runId)
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.rows.0.providers.moodle.status', 'queued');
    $this->app->call([$job, 'handle']);

    $this->getJson('/api/v1/admin/users/import/'.$runId)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.summary.provider_success_count', 1);
});

it('reports independent failures without rolling back the local user', function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE, PermissionEnum::IMPORT_RESULTS]);
    $moodle = $this->mock(App\Contracts\Integrations\MoodleClientContract::class);
    $moodle->shouldReceive('isEnabled')->once()->andReturnTrue();
    $moodle->shouldReceive('assertConfigured')->once();
    $moodle->shouldReceive('findOrCreateUser')->once()->andThrow(new App\Exceptions\Integrations\UnrecoverableProvisioningException('secret provider payload'));
    $niliroom = $this->mock(App\Contracts\Integrations\NiliroomClientContract::class);
    $niliroom->shouldReceive('isEnabled')->once()->andReturnTrue();
    $niliroom->shouldReceive('assertConfigured')->once();
    $niliroom->shouldReceive('ensureUser')->once()->andReturn('identity-1');
    $runId = postImportPreview($this, userImportFile([userImportRow(['provision_moodle' => 'true', 'provision_niliroom' => 'true'])]))->json('data.run_id');
    postImportApproval($this, $runId)->assertSuccessful();
    $row = App\Models\ImportRun::query()->where('uuid', $runId)->firstOrFail()->rows()->firstOrFail();

    $this->app->call([new App\Jobs\Provisioning\ProvisionImportUserProviderJob($row->id, App\Enums\ImportExport\UserProvisioningProviderEnum::MOODLE), 'handle']);
    $this->getJson('/api/v1/admin/users/import/'.$runId)->assertJsonPath('data.status', 'processing');
    $this->app->call([new App\Jobs\Provisioning\ProvisionImportUserProviderJob($row->id, App\Enums\ImportExport\UserProvisioningProviderEnum::NILIROOM), 'handle']);

    $response = $this->getJson('/api/v1/admin/users/import/'.$runId);
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'completed_with_provider_failures')
        ->assertJsonPath('data.summary.provider_failure_count', 1)
        ->assertJsonPath('data.summary.provider_success_count', 1)
        ->assertJsonPath('data.rows.0.providers.moodle.status', 'failed')
        ->assertJsonPath('data.rows.0.providers.niliroom.status', 'succeeded');
    expect(json_encode($response->json()))->not->toContain('secret provider payload');
    expect(App\Models\User::query()->where('phone', '09123456789')->exists())->toBeTrue();
});

it('reports completion after the account job succeeds and does not create the account twice', function (): void {
    Storage::fake('local');
    Queue::fake();
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE, PermissionEnum::IMPORT_RESULTS]);
    $client = $this->mock(App\Contracts\Integrations\MoodleClientContract::class);
    $client->shouldReceive('isEnabled')->once()->andReturnTrue();
    $client->shouldReceive('assertConfigured')->once();
    $client->shouldReceive('findOrCreateUser')->once()->andReturn([123, '0000000019']);
    $runId = postImportPreview($this, userImportFile([userImportRow(['provision_moodle' => 'true'])]))->json('data.run_id');
    postImportApproval($this, $runId)->assertSuccessful();
    $row = App\Models\ImportRun::query()->where('uuid', $runId)->firstOrFail()->rows()->firstOrFail();
    $job = new App\Jobs\Provisioning\ProvisionImportUserProviderJob($row->id, App\Enums\ImportExport\UserProvisioningProviderEnum::MOODLE);

    $this->app->call([$job, 'handle']);
    $this->app->call([$job, 'handle']);

    $this->getJson('/api/v1/admin/users/import/'.$runId)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.summary.provider_success_count', 1)
        ->assertJsonPath('data.rows.0.providers.moodle', ['status' => 'succeeded', 'message' => null]);
});

it('preserves a report reference after deletion fails and retries safely', function (bool $removeErrors): void {
    Storage::fake('local');
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_RESULTS]);
    $runId = postImportPreview($this, userImportFile([userImportRow(['first_name' => null])]))->json('data.run_id');
    $this->getJson('/api/v1/admin/users/import/'.$runId)->assertJsonPath('data.error_report.available', true);
    $run            = App\Models\ImportRun::query()->where('uuid', $runId)->firstOrFail();
    $oldPath        = $run->error_report_path;
    $oldFingerprint = $run->error_report_fingerprint;
    $row            = $run->rows()->sole();
    $row->update(['is_valid' => ! $removeErrors ? false : true, 'errors' => $removeErrors ? [] : [
        ['field' => 'phone', 'code' => 'invalid', 'message' => 'Invalid phone'],
    ]]);
    $root    = Storage::disk('local')->path('');
    $adapter = new class($root) extends League\Flysystem\Local\LocalFilesystemAdapter
    {
        public bool $failDeletion = true;

        public function delete(string $path): void
        {
            if ($this->failDeletion) {
                throw League\Flysystem\UnableToDeleteFile::atLocation($path);
            }

            parent::delete($path);
        }
    };
    Storage::set('local', new Illuminate\Filesystem\FilesystemAdapter(
        new League\Flysystem\Filesystem($adapter), $adapter, ['root' => $root],
    ));

    $this->getJson('/api/v1/admin/users/import/'.$runId)->assertSuccessful()
        ->assertJsonPath('data.error_report.available', false);

    expect($run->fresh()->error_report_path)->toBe($oldPath)
        ->and($run->fresh()->error_report_fingerprint)->toBe($oldFingerprint);
    Storage::disk('local')->assertExists($oldPath);
    $adapter->failDeletion = false;

    $this->getJson('/api/v1/admin/users/import/'.$runId)->assertSuccessful()
        ->assertJsonPath('data.error_report.available', ! $removeErrors);

    Storage::disk('local')->assertMissing($oldPath);
    if ($removeErrors) {
        expect($run->fresh()->error_report_path)->toBeNull();
    } else {
        expect($run->fresh()->error_report_path)->not->toBe($oldPath);
        Storage::disk('local')->assertExists($run->fresh()->error_report_path);
    }
})->with(['replacement' => false, 'removal' => true]);

it('returns a persisted run with no identity key', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_RESULTS]);
    $run = App\Models\ImportRun::factory()->create(['identity_key' => null]);

    $this->getJson('/api/v1/admin/users/import/'.$run->uuid)
        ->assertSuccessful()
        ->assertJsonPath('data.identity_key', null);
});
