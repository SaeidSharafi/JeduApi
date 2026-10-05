<?php

declare(strict_types=1);

namespace App\Jobs\Provisioning;

use App\Actions\Admin\ImportExport\ProvisionImportUserAction;
use App\Enums\ImportExport\UserProvisioningProviderEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class ProvisionImportUserProviderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = ProvisionImportUserAction::MAX_ATTEMPTS;

    public int $timeout = 60;

    public bool $failOnTimeout;

    public function __construct(public readonly int $rowId, public readonly UserProvisioningProviderEnum $provider)
    {
        $this->failOnTimeout = $provider === UserProvisioningProviderEnum::IMS;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 180];
    }

    public function failed(?Throwable $exception): void
    {
        app(ProvisionImportUserAction::class)->fail($this->rowId, $this->provider, $exception);
    }

    public function handle(ProvisionImportUserAction $action): void
    {
        $action->handle($this->rowId, $this->provider);
    }
}
