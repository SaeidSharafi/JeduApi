<?php

declare(strict_types=1);

namespace App\Contracts\Cache;

use App\Enums\System\CacheKey;
use App\Enums\System\CacheTag;
use Closure;

/**
 * The only entry point application code uses to read and write cached values.
 *
 * Keys are always a {@see CacheKey} case plus its named parameters, never a raw
 * string, so a key, its lifetime and its invalidation tag can only be declared
 * in one place. Values equal to null are never stored: the gateway treats a
 * null read as a miss and callbacks that return null leave the key empty.
 */
interface CacheStore
{
    /**
     * Read the value stored for the given registry key.
     *
     * @param  array<string, scalar|null>  $params
     */
    public function get(CacheKey $key, array $params = [], mixed $default = null): mixed;

    /**
     * Store a value using the lifetime declared on the registry key.
     *
     * A null value is ignored, because the gateway never stores null.
     *
     * @param  array<string, scalar|null>  $params
     */
    public function put(CacheKey $key, array $params, mixed $value): void;

    /**
     * Return the stored value, generating and storing it through the callback when missing.
     *
     * @template T
     *
     * @param  array<string, scalar|null>  $params
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(CacheKey $key, array $params, Closure $callback): mixed;

    /**
     * Return the stored value, generating and storing it forever when missing.
     *
     * @template T
     *
     * @param  array<string, scalar|null>  $params
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberForever(CacheKey $key, array $params, Closure $callback): mixed;

    /**
     * Return a fresh value, or a stale one while refreshing after the response.
     *
     * The refresh always runs under a single-flight lock applied by the implementation,
     * so a cache stampede cannot be reintroduced by a caller that omits it.
     *
     * @template T
     *
     * @param  array<string, scalar|null>  $params
     * @param  Closure(): T  $callback
     * @return T
     */
    public function flexible(CacheKey $key, array $params, Closure $callback): mixed;

    /**
     * Delete the exact key, leaving its invalidation generation untouched.
     *
     * @param  array<string, scalar|null>  $params
     */
    public function forget(CacheKey $key, array $params = []): void;

    /**
     * Make every key of each tag unreachable by bumping that tag's version counter.
     */
    public function invalidate(CacheTag ...$tags): void;
}
