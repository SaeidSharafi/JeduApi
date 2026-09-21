<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use App\Enums\System\CacheTag;
use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Factory;
use InvalidArgumentException;

/**
 * Laravel-backed implementation of the cache gateway.
 *
 * This is the only class allowed to reach into Laravel's cache. Keys are always
 * composed here from a registry case, so a tag's version counter is applied in
 * exactly one place and a group invalidation only ever bumps that counter.
 */
final class LaravelCacheStore implements CacheStore
{
    /**
     * Seconds the single-flight refresh lock is held while flexible() regenerates a value.
     */
    private const int REFRESH_LOCK_SECONDS = 30;

    /**
     * Seconds a tag's version counter lives; long enough to outlive every TTL it guards.
     */
    private const int VERSION_TTL_SECONDS = 315_360_000;

    public function __construct(private readonly Factory $cache) {}

    public function get(CacheKey $key, array $params = [], mixed $default = null): mixed
    {
        return $this->repository()->get($this->key($key, $params), $default);
    }

    public function put(CacheKey $key, array $params, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $this->repository()->put($this->key($key, $params), $value, $key->ttl());
    }

    public function remember(CacheKey $key, array $params, Closure $callback): mixed
    {
        return $this->rememberUsing($key, $params, $callback, $key->ttl());
    }

    public function rememberForever(CacheKey $key, array $params, Closure $callback): mixed
    {
        return $this->rememberUsing($key, $params, $callback, null);
    }

    public function flexible(CacheKey $key, array $params, Closure $callback): mixed
    {
        $ttl      = $key->ttl();
        $staleTtl = $key->staleTtl();

        if ($ttl === null || $staleTtl === null) {
            throw new InvalidArgumentException(sprintf(
                'Cache key [%s] must declare a ttl and a stale ttl to be read through flexible().',
                $key->name,
            ));
        }

        return $this->repository()->flexible(
            $this->key($key, $params),
            [$ttl, $ttl + $staleTtl],
            $callback,
            ['seconds' => self::REFRESH_LOCK_SECONDS],
        );
    }

    public function forget(CacheKey $key, array $params = []): void
    {
        $this->repository()->forget($this->key($key, $params));
    }

    public function version(CacheTag $tag): int
    {
        return (int) $this->repository()->get($this->versionKey($tag), 0);
    }

    /**
     * Make every key of each tag unreachable by bumping that tag's version counter.
     *
     * The counter is seeded with an explicit lifetime before it is incremented:
     * the database store refuses to increment a missing key, and a zero lifetime
     * would let the counter expire while the values it guards are still stored.
     */
    public function invalidate(CacheTag ...$tags): void
    {
        foreach ($tags as $tag) {
            $versionKey = $this->versionKey($tag);

            $this->repository()->add($versionKey, 0, self::VERSION_TTL_SECONDS);
            $this->repository()->increment($versionKey);
        }
    }

    /**
     * Generate and store the value on a miss, never storing null.
     *
     * @param  array<string, scalar|null>  $params
     * @param  Closure(): mixed  $callback
     * @param  int|null  $ttl  Lifetime in seconds; null stores the value forever.
     */
    private function rememberUsing(CacheKey $key, array $params, Closure $callback, ?int $ttl): mixed
    {
        $cached = $this->get($key, $params);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();

        if ($value !== null) {
            $this->repository()->put($this->key($key, $params), $value, $ttl);
        }

        return $value;
    }

    /**
     * The stored key: the tag and its current generation, followed by the resolved template.
     *
     * @param  array<string, scalar|null>  $params
     */
    private function key(CacheKey $key, array $params): string
    {
        return sprintf(
            'cache:%s:v%d:%s',
            $key->group()->value,
            $this->version($key->group()),
            $key->resolve($params),
        );
    }

    private function versionKey(CacheTag $tag): string
    {
        return 'cache.version:'.$tag->value;
    }

    private function repository(): Repository
    {
        /** @var Repository $repository */
        $repository = $this->cache->store();

        return $repository;
    }
}
