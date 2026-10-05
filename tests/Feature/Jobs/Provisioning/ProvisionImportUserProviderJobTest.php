<?php

declare(strict_types=1);

use App\Enums\ImportExport\UserProvisioningProviderEnum;
use App\Jobs\Provisioning\ProvisionImportUserProviderJob;
use Illuminate\Support\Facades\Queue;

covers(ProvisionImportUserProviderJob::class);

it('queues a timeout policy that permits replay only for safe account providers', function (UserProvisioningProviderEnum $provider, bool $shouldFail): void {
    Queue::connection('database')->push(new ProvisionImportUserProviderJob(1, $provider));

    $queuedJob = Queue::connection('database')->pop();

    expect($queuedJob->shouldFailOnTimeout())->toBe($shouldFail);
})->with([
    'Moodle reconciles'             => [UserProvisioningProviderEnum::MOODLE, false],
    'Skyroom reconciles'            => [UserProvisioningProviderEnum::SKYROOM, false],
    'Niliroom upserts'              => [UserProvisioningProviderEnum::NILIROOM, false],
    'IMS needs manual verification' => [UserProvisioningProviderEnum::IMS, true],
]);
