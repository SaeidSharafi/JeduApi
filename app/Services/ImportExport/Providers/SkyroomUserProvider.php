<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Providers;

use App\Contracts\ImportExport\UserProvisioningProvider;
use App\Contracts\Integrations\SkyroomClientContract;
use App\Exceptions\Integrations\UnrecoverableProvisioningException;
use App\Models\User;

final readonly class SkyroomUserProvider implements UserProvisioningProvider
{
    public function __construct(private SkyroomClientContract $client) {}

    public function ensureUser(User $user): void
    {
        if (! $this->client->isEnabled()) {
            throw new UnrecoverableProvisioningException('Provider is disabled.');
        }
        $this->client->assertConfigured();
        $this->client->findOrCreateUser($user);
    }

    public function canReplay(): bool
    {
        return true;
    }
}
