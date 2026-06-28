<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Contracts\CartIdentifier;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestCartIdentifier implements CartIdentifier
{
    private ?string $resolvedGuestToken = null;

    public function __construct(
        private readonly Request $request,
        private readonly Guard $auth
    ) {}

    public function userId(): ?int
    {
        if ($this->auth->check()) {
            return (int) $this->auth->id();
        }

        return null;
    }

    public function guestToken(): ?string
    {
        if ($this->auth->check()) {
            return null;
        }

        if ($this->resolvedGuestToken) {
            return $this->resolvedGuestToken;
        }

        // Prefer header for headless APIs
        $incoming = $this->request->headers->get('X-Guest-Token') ?? '';
        if ($incoming !== '' && Str::isUuid($incoming)) {
            $this->resolvedGuestToken = $incoming;

            return $this->resolvedGuestToken;
        }

        return null;
    }

    public function isGuest(): bool
    {
        return ! $this->auth->check();
    }

    public function ensureGuestToken(): string
    {
        if ($this->resolvedGuestToken && Str::isUuid($this->resolvedGuestToken)) {
            return $this->resolvedGuestToken;
        }

        $this->resolvedGuestToken = (string) Str::uuid();

        return $this->resolvedGuestToken;
    }
}
