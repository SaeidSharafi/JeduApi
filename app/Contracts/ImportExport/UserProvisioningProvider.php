<?php

declare(strict_types=1);

namespace App\Contracts\ImportExport;

use App\Models\User;

/** Standalone account capability; it never enrolls a user or issues access grants. */
interface UserProvisioningProvider
{
    public function ensureUser(User $user): void;

    /** Whether replay after a crash or an ambiguous response is safe. */
    public function canReplay(): bool;
}
