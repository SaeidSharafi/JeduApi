<?php

declare(strict_types=1);

use App\Actions\Admin\ImportExport\ProvisionImportUserAction;
use App\Contracts\Integrations\MoodleClientContract;
use App\Enums\ImportExport\ImportRunStatusEnum;
use App\Enums\ImportExport\UserProvisioningProviderEnum;
use App\Exceptions\Integrations\RecoverableProvisioningException;
use App\Jobs\Provisioning\ProvisionImportUserProviderJob;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;

covers(ProvisionImportUserAction::class);

it('exhausts retries after three failures without calling the provider again', function (): void {
    $user = User::factory()->create();
    $run  = ImportRun::factory()->create(['approved_at' => now()]);
    $row  = ImportRunRow::factory()->for($run)->create([
        'local_resource_id' => (string) $user->id,
        'providers'         => ['moodle' => ['status' => 'queued', 'message' => null, 'attempts' => 0]],
    ]);
    $client = $this->mock(MoodleClientContract::class);
    $client->shouldReceive('isEnabled')->times(3)->andReturnTrue();
    $client->shouldReceive('assertConfigured')->times(3);
    $client->shouldReceive('findOrCreateUser')->times(3)->andThrow(new RecoverableProvisioningException('outage'));
    $job = new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::MOODLE);

    expect(fn () => $this->app->call([$job, 'handle']))->toThrow(RecoverableProvisioningException::class);
    expect(fn () => $this->app->call([$job, 'handle']))->toThrow(RecoverableProvisioningException::class);
    $this->app->call([$job, 'handle']);
    $this->app->call([$job, 'handle']);

    expect($run->fresh()->status->value)->toBe('completed_with_provider_failures');
    expect($row->fresh()->providers['moodle']['status'])->toBe('retryable_failed');
});

it('requires manual verification for a previous uncertain IMS attempt in another run', function (string $status): void {
    $user = User::factory()->create();
    $run  = ImportRun::factory()->create(['approved_at' => now()]);
    $row  = ImportRunRow::factory()->for($run)->create([
        'local_resource_id' => (string) $user->id,
        'providers'         => ['ims' => ['status' => 'queued', 'message' => null]],
    ]);
    App\Models\UserProviderAccount::factory()->for($user)->create(['provider' => UserProvisioningProviderEnum::IMS, 'status' => $status]);
    $this->mock(App\Contracts\Integrations\ImsClientContract::class)->shouldNotReceive('storeStudent');

    $this->app->call([new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::IMS), 'handle']);

    expect($row->fresh()->providers['ims'])->toMatchArray([
        'status' => 'failed', 'message' => __('imports.provider_results.manual_verification'),
    ]);
})->with(['worker interrupted' => ['processing'], 'ambiguous response' => ['ambiguous']]);

it('reconciles a replay-safe account after an interrupted processing attempt', function (): void {
    $user = User::factory()->create();
    $run  = ImportRun::factory()->create([
        'approved_at'          => now(),
        'status'               => ImportRunStatusEnum::PROCESSING,
        'artifacts_expires_at' => now()->subDay(),
    ]);
    $row = ImportRunRow::factory()->for($run)->create([
        'local_resource_id' => (string) $user->id,
        'providers'         => ['moodle' => ['status' => 'processing', 'message' => null, 'attempts' => 1]],
    ]);
    App\Models\UserProviderAccount::factory()->for($user)->create(['status' => 'processing']);
    $client = $this->mock(MoodleClientContract::class);
    $client->shouldReceive('isEnabled')->once()->andReturnTrue();
    $client->shouldReceive('assertConfigured')->once();
    $client->shouldReceive('findOrCreateUser')->once()->andReturn([7, 'existing-account']);

    $this->app->call([new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::MOODLE), 'handle']);

    expect($row->fresh()->providers['moodle']['status'])->toBe('succeeded');
    expect($run->fresh()->artifacts_expires_at->isFuture())->toBeTrue();
});

it('does not exceed the persisted attempt limit after interrupted work', function (): void {
    $user = User::factory()->create();
    $run  = ImportRun::factory()->create(['approved_at' => now()]);
    $row  = ImportRunRow::factory()->for($run)->create([
        'local_resource_id' => (string) $user->id,
        'providers'         => ['moodle' => ['status' => 'processing', 'message' => null, 'attempts' => 3]],
    ]);
    App\Models\UserProviderAccount::factory()->for($user)->create(['status' => 'processing']);
    $this->mock(MoodleClientContract::class)->shouldNotReceive('findOrCreateUser');

    $this->app->call([new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::MOODLE), 'handle']);

    expect($row->fresh()->providers['moodle']['status'])->toBe('retryable_failed');
});

it('records exhausted replay-safe worker timeouts as retryable failures', function (): void {
    $run = ImportRun::factory()->create(['approved_at' => now()]);
    $row = ImportRunRow::factory()->for($run)->create([
        'providers' => ['moodle' => ['status' => 'processing', 'message' => null, 'attempts' => 3]],
    ]);

    (new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::MOODLE))
        ->failed(new Illuminate\Queue\TimeoutExceededException('worker timeout'));

    expect($run->fresh()->status->value)->toBe('completed_with_provider_failures');
    expect($row->fresh()->providers['moodle']['status'])->toBe('retryable_failed');
});

it('ignores a late failure callback after a provider has already succeeded', function (): void {
    $run = ImportRun::factory()->create(['approved_at' => now(), 'status' => ImportRunStatusEnum::COMPLETED]);
    $row = ImportRunRow::factory()->for($run)->create([
        'providers' => ['moodle' => ['status' => 'succeeded', 'message' => null]],
    ]);

    (new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::MOODLE))->failed(new RuntimeException('late callback'));

    expect($row->fresh()->providers['moodle']['status'])->toBe('succeeded');
    expect($run->fresh()->status->value)->toBe('completed');
});

it('creates standalone accounts on the other supported providers', function (UserProvisioningProviderEnum $provider, string $contract, string $method, array $response): void {
    $user = User::factory()->create();
    $run  = ImportRun::factory()->create(['approved_at' => now()]);
    $row  = ImportRunRow::factory()->for($run)->create([
        'local_resource_id' => (string) $user->id,
        'providers'         => [$provider->value => ['status' => 'queued', 'message' => null]],
    ]);
    $client = $this->mock($contract);
    $client->shouldReceive('isEnabled')->once()->andReturnTrue();
    $client->shouldReceive('assertConfigured')->once();
    $client->shouldReceive($method)->once()->andReturn($response);

    $this->app->call([new ProvisionImportUserProviderJob($row->id, $provider), 'handle']);

    expect($run->fresh()->status->value)->toBe('completed');
    expect($row->fresh()->providers[$provider->value]['status'])->toBe('succeeded');
})->with([
    'IMS student account'  => [UserProvisioningProviderEnum::IMS, App\Contracts\Integrations\ImsClientContract::class, 'storeStudent', ['data' => ['student_id' => 7]]],
    'Skyroom user account' => [UserProvisioningProviderEnum::SKYROOM, App\Contracts\Integrations\SkyroomClientContract::class, 'findOrCreateUser', ['skyroom_user_id' => 7]],
]);

it('does not repeat a successful account creation in another import run', function (): void {
    $user = User::factory()->create();
    $run  = ImportRun::factory()->create(['approved_at' => now()]);
    $row  = ImportRunRow::factory()->for($run)->create([
        'local_resource_id' => (string) $user->id,
        'providers'         => ['moodle' => ['status' => 'queued', 'message' => null]],
    ]);
    App\Models\UserProviderAccount::factory()->for($user)->create();
    $this->mock(MoodleClientContract::class)->shouldNotReceive('findOrCreateUser');

    $this->app->call([new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::MOODLE), 'handle']);

    expect($row->fresh()->providers['moodle']['status'])->toBe('succeeded');
});

it('does not replay an ambiguous IMS student creation', function (): void {
    $user = User::factory()->create();
    $run  = ImportRun::factory()->create(['approved_at' => now()]);
    $row  = ImportRunRow::factory()->for($run)->create([
        'local_resource_id' => (string) $user->id,
        'providers'         => ['ims' => ['status' => 'queued', 'message' => null]],
    ]);
    $client = $this->mock(App\Contracts\Integrations\ImsClientContract::class);
    $client->shouldReceive('isEnabled')->once()->andReturnTrue();
    $client->shouldReceive('assertConfigured')->once();
    $client->shouldReceive('storeStudent')->once()->andThrow(new RecoverableProvisioningException('ambiguous IMS response'));
    $job = new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::IMS);

    $this->app->call([$job, 'handle']);
    $this->app->call([$job, 'handle']);

    expect($run->fresh()->status->value)->toBe('completed_with_provider_failures');
    expect($row->fresh()->providers['ims']['status'])->toBe('failed');
    expect(App\Models\UserProviderAccount::query()->where('user_id', $user->id)->value('status'))->toBe('ambiguous');
});

it('leaves unapproved or unrequested rows untouched', function (bool $approved, bool $requested): void {
    $run = ImportRun::factory()->create(['approved_at' => $approved ? now() : null]);
    $row = ImportRunRow::factory()->for($run)->create([
        'providers' => $requested ? ['moodle' => ['status' => 'queued', 'message' => null]] : [],
    ]);
    $this->mock(MoodleClientContract::class)->shouldNotReceive('findOrCreateUser');

    $this->app->call([new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::MOODLE), 'handle']);

    expect($row->fresh()->providers)->toBe($row->providers);
    expect(App\Models\UserProviderAccount::query()->exists())->toBeFalse();
})->with(['unapproved' => [false, true], 'unrequested' => [true, false]]);

it('finishes the run when the worker terminates a queued job', function (): void {
    $run = ImportRun::factory()->create(['approved_at' => now()]);
    $row = ImportRunRow::factory()->for($run)->create([
        'providers' => ['moodle' => ['status' => 'queued', 'message' => null]],
    ]);
    $job = new ProvisionImportUserProviderJob($row->id, UserProvisioningProviderEnum::MOODLE);

    $job->failed(new RuntimeException('worker failure containing private details'));

    expect($run->fresh()->status->value)->toBe('completed_with_provider_failures');
    expect($row->fresh()->providers['moodle']['status'])->toBe('failed');
    expect($row->fresh()->providers['moodle']['message'])->not->toContain('private details');
});
