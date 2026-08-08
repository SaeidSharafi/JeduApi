<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected bool $isDataCached = true;

    /**
     * Cache the token lookup from the database.
     *
     * @param  string  $token
     */
    public static function findToken($token): ?SanctumPersonalAccessToken
    {
        $plainToken  = str_contains($token, '|') ? explode('|', $token, 2)[1] : $token;
        $hashedToken = hash('sha256', $plainToken);

        $tokenInstance = cache()->remember("AccessToken::{$hashedToken}", 360, function () use ($token) {
            return parent::findToken($token) ?: '_null_';
        });

        if ($tokenInstance instanceof SanctumPersonalAccessToken) {
            return $tokenInstance;
        }

        return null;
    }

    public function getTokenableAttribute(mixed $value): ?Model
    {
        return cache()->remember("token_{$this->id}::id_".app()->environment(), 360, function (): ?Model {
            $this->isDataCached = false;

            return parent::tokenable()->first();
        });
    }

    /**
     * Limit saving of records to avoid database writes when only "last_used_at" updates.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        $changes = $this->getDirty();

        // Only save to DB if we actually changed data other than last_used_at/updated_at
        if (! $this->isDataCached || ! array_key_exists('last_used_at', $changes) || count($changes) > 2) {
            return parent::save($options);
        }

        return false;
    }

    /**
     * Automatically clean up cache entries when a token is deleted (e.g. on logout)
     */
    protected static function booted(): void
    {
        self::deleted(function ($token): void {
            // "token" attribute holds the SHA-256 hash in the database
            cache()->forget('AccessToken::'.$token->token);
            cache()->forget("token_{$token->id}::id_".app()->environment());
        });
    }
}
