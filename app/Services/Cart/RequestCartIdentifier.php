<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Contracts\CartIdentifier;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestCartIdentifier implements CartIdentifier
{
    private ?int $resolvedUserId = null;
    private ?string $resolvedGuestToken = null;

    public function __construct(
        private readonly Request $request,
        private readonly Guard $auth
    ) {
        $this->boot();
    }

    private function boot(): void
    {
        if ($this->auth->check()) {
            $this->resolvedUserId    = (int) $this->auth->id();
            $this->resolvedGuestToken = null;
            return;
        }

        // Prefer header for headless APIs
        $incoming = (string) ($this->request->headers->get('X-Guest-Token') ?? '');
        if ($incoming !== '' && Str::isUuid($incoming)) {
            $this->resolvedGuestToken = $incoming;
        }
    }

    public function userId(): ?int
    {
        return $this->resolvedUserId;
    }

    public function guestToken(): ?string
    {
        return $this->resolvedGuestToken;
    }

    public function isGuest(): bool
    {
        return $this->resolvedUserId === null;
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
