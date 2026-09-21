<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

final class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Cached in place of a token that resolves to nothing, so a rejected bearer
     * token is answered from the cache instead of the database. The gateway
     * never stores null, so a miss needs an explicit sentinel.
     */
    private const string MISS = '_null_';

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

        $tokenInstance = self::cacheStore()->remember(
            CacheKey::AccessToken,
            ['hash' => $hashedToken],
            function () use ($token): SanctumPersonalAccessToken|string {
                return parent::findToken($token) ?? self::MISS;
            },
        );

        if ($tokenInstance instanceof SanctumPersonalAccessToken) {
            return $tokenInstance;
        }

        return null;
    }

    /**
     * Drop the cached lookup and user snapshot of every token a model owns.
     *
     * Banning or deleting an account revokes its tokens with a bulk delete,
     * which fires no model events, so those actions call this first; otherwise
     * a revoked token keeps authenticating until its cached entry expires.
     */
    public static function forgetCacheFor(User|Staff $tokenable): void
    {
        $tokenable->tokens()->get(['id', 'token'])->each(function (SanctumPersonalAccessToken $token): void {
            self::forgetKeys($token->token, $token->id);
        });
    }

    public function getTokenableAttribute(mixed $value): ?Model
    {
        return self::cacheStore()->remember(
            CacheKey::Tokenable,
            ['id' => $this->id, 'env' => app()->environment()],
            function (): ?Model {
                $this->isDataCached = false;

                return parent::tokenable()->first();
            },
        );
    }

    /**
     * Drop both cached entries owned by this token.
     *
     * A single model delete (logout) reaches this through the `deleted` event.
     */
    public function forgetCache(): void
    {
        self::forgetKeys($this->token, $this->id);
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
        self::deleted(function (self $token): void {
            $token->forgetCache();
        });
    }

    private static function forgetKeys(string $token, int|string $id): void
    {
        $cache = self::cacheStore();

        $cache->forget(CacheKey::AccessToken, ['hash' => $token]);
        $cache->forget(CacheKey::Tokenable, [
            'id'  => $id,
            'env' => app()->environment(),
        ]);
    }

    private static function cacheStore(): CacheStore
    {
        return app(CacheStore::class);
    }
}
