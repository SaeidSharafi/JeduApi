<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Providers;

use App\Contracts\ImportExport\UserProvisioningProvider;
use App\Contracts\Integrations\NiliroomClientContract;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;

final readonly class NiliroomUserProvider implements UserProvisioningProvider
{
    public function __construct(private NiliroomClientContract $client) {}

    public function ensureUser(User $user): void
    {
        if (! $this->client->isEnabled()) {
            throw new UnrecoverableProvisioningException('Provider is disabled.');
        }
        $this->client->assertConfigured();
        $this->client->ensureUser($user);
    }

    public function canReplay(): bool
    {
        return true;
    }
}
