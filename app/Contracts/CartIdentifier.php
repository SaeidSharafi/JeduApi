<?php

declare(strict_types=1);

namespace App\Contracts;

interface CartIdentifier
{
    /**
     * Returns the authenticated user's ID if available, otherwise null.
     */
    public function userId(): ?int;

    /**
     * Returns the current guest token if present on the request; does not generate a new one.
     */
    public function guestToken(): ?string;

    /**
     * True when the current caller is not an authenticated user.
     */
    public function isGuest(): bool;

    /**
     * Ensure a guest token exists and return it. Generates and memoizes a UUID if missing.
     */
    public function ensureGuestToken(): string;
}
