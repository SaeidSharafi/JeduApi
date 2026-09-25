<?php

declare(strict_types=1);

use App\Actions\Admin\ImportExport\ApproveImportRunAction;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

covers(ApproveImportRunAction::class);

beforeEach(function (): void {
    Storage::fake('local');
});

it('rejects guests', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))->json('data.run_id');
    $this->app['auth']->forgetGuards();

    postImportApproval($this, $runId)->assertUnauthorized();
});

it('rejects staff without the approval permission', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))->json('data.run_id');
    $this->unauthorized_user();

    postImportApproval($this, $runId)->assertForbidden();
});

it('creates all valid users with an empty approval body and redacts passwords', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([
        userImportRow(['password' => 'secret-pass-123']),
    ]))->json('data.run_id');

    $response = postImportApproval($this, $runId);

    $response->assertSuccessful();
    $response->assertJsonPath('data.run_id', $runId);
    $response->assertJsonPath('data.resource', 'users');
    $response->assertJsonPath('data.status', ImportRunStatusEnum::PROCESSING->value);
    $response->assertJsonPath('data.summary', [
        'created_count'         => 1,
        'updated_count'         => 0,
        'provider_queued_count' => 0,
    ]);
    $response->assertJsonMissingPath('data.password');
    expect(json_encode($response->json()))->not->toContain('secret-pass-123');

    $user = User::query()->where('phone', '09123456789')->firstOrFail();
    expect($user->first_name)->toBe('علی')
        ->and(Hash::check('secret-pass-123', (string) $user->password))->toBeTrue()
        ->and(ImportRun::query()->where('uuid', $runId)->firstOrFail()->approved_at)->not->toBeNull();
});

it('requires explicit confirmation before importing valid rows from a partial preview', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([
        userImportRow(['phone' => 'invalid', 'civil_id' => 'P1234567', 'civil_id_type' => 'passport']),
        userImportRow(),
    ]))->json('data.run_id');

    postImportApproval($this, $runId)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('include_valid_rows');

    expect(User::query()->where('phone', '09123456789')->exists())->toBeFalse();

    postImportApproval($this, $runId, ['include_valid_rows' => true])
        ->assertSuccessful()
        ->assertJsonPath('data.summary.created_count', 1);
});

it('rejects approval when the preview has no valid rows', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([
        userImportRow(['phone' => 'invalid']),
    ]))->json('data.run_id');

    postImportApproval($this, $runId, ['include_valid_rows' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('run');

    expect(User::query()->exists())->toBeFalse();
});

it('updates only supplied values and preserves an empty optional password', function (): void {
    $existing = User::factory()->withPassword()->create([
        'phone'      => '09123456789',
        'email'      => 'old@example.com',
        'first_name' => 'قدیمی',
    ]);
    $password = $existing->password;
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $preview = postImportPreview($this, userImportFile([
        userImportRow([
            'phone'    => '09123456789', 'first_name' => 'جدید', 'email' => '', 'password' => '',
            'civil_id' => 'P1234567', 'civil_id_type' => 'passport',
        ]),
    ]));
    $preview->assertSuccessful();
    $runId = $preview->json('data.run_id');

    postImportApproval($this, $runId)
        ->assertSuccessful()
        ->assertJsonPath('data.summary.updated_count', 1);

    $existing->refresh();
    expect($existing->first_name)->toBe('جدید')
        ->and($existing->email)->toBe('old@example.com')
        ->and($existing->password)->toBe($password);
});

it('returns the original result when approval is repeated without repeating mutation', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))->json('data.run_id');

    $first     = postImportApproval($this, $runId);
    $user      = User::query()->where('phone', '09123456789')->firstOrFail();
    $updatedAt = $user->updated_at;
    $second    = postImportApproval($this, $runId);

    $first->assertSuccessful();
    $second->assertSuccessful();
    expect($second->json('data'))->toBe($first->json('data'))
        ->and(User::query()->where('phone', '09123456789')->count())->toBe(1)
        ->and($user->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('uses the durable approval marker for idempotency after status changes', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))->json('data.run_id');
    postImportApproval($this, $runId)->assertSuccessful();
    $run = ImportRun::query()->where('uuid', $runId)->firstOrFail();
    $run->update(['status' => ImportRunStatusEnum::PREVIEW_READY]);
    $user = User::query()->where('phone', '09123456789')->firstOrFail();
    $user->update(['first_name' => 'پس از تأیید']);

    postImportApproval($this, $runId)
        ->assertSuccessful()
        ->assertJsonPath('data.summary.created_count', 1);

    expect($user->fresh()->first_name)->toBe('پس از تأیید')
        ->and(User::query()->where('phone', '09123456789')->count())->toBe(1);
});

it('rolls back every local mutation when one valid row cannot be persisted', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([
        userImportRow(['civil_id' => 'P1234567', 'civil_id_type' => 'passport']),
        userImportRow([
            'phone'    => '09120000000', 'email' => 'second@example.com',
            'civil_id' => 'P7654321', 'civil_id_type' => 'passport',
        ]),
    ]))->json('data.run_id');
    User::creating(function (User $user): void {
        if ($user->phone === '09120000000') {
            throw new RuntimeException('forced persistence failure');
        }
    });

    postImportApproval($this, $runId)->assertServerError();

    expect(User::query()->whereIn('phone', ['09123456789', '09120000000'])->exists())->toBeFalse()
        ->and(ImportRun::query()->where('uuid', $runId)->value('status'))->toBe(ImportRunStatusEnum::PREVIEW_READY);
});

it('rejects identity drift without mutating another user', function (): void {
    $original = User::factory()->create(['phone' => '09123456789']);
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $preview = postImportPreview($this, userImportFile([
        userImportRow([
            'phone'    => '09123456789', 'first_name' => 'واردشده',
            'civil_id' => 'P1234567', 'civil_id_type' => 'passport',
        ]),
    ]));
    $preview->assertSuccessful();
    $runId = $preview->json('data.run_id');
    $original->update(['phone' => '09121111111']);
    $replacement = User::factory()->create(['phone' => '09123456789', 'first_name' => 'جایگزین']);

    postImportApproval($this, $runId)->assertUnprocessable();

    expect($replacement->fresh()->first_name)->toBe('جایگزین')
        ->and($original->fresh()->phone)->toBe('09121111111');
});

it('rejects a changed private upload before mutation', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))->json('data.run_id');
    $run   = ImportRun::query()->where('uuid', $runId)->firstOrFail();
    Storage::disk('local')->put($run->file_path, 'changed');

    postImportApproval($this, $runId)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('run');

    expect(User::query()->where('phone', '09123456789')->exists())->toBeFalse();
});

it('rejects a changed normalized snapshot before mutation', function (): void {
    $this->authorized_user([PermissionEnum::IMPORT_PREVIEW, PermissionEnum::IMPORT_APPROVE]);
    $runId = postImportPreview($this, userImportFile([userImportRow()]))->json('data.run_id');
    $run   = ImportRun::query()->where('uuid', $runId)->firstOrFail();
    $row   = $run->rows()->firstOrFail();
    $row->update(['data' => [...$row->data, 'first_name' => 'دستکاری‌شده']]);

    postImportApproval($this, $runId)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('run');

    expect(User::query()->where('phone', '09123456789')->exists())->toBeFalse();
});
