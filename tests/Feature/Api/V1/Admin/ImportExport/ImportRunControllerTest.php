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
