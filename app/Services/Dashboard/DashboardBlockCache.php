<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;
use Carbon\CarbonImmutable;
use Closure;
use Hekmatinasser\Verta\Verta;

/**
 * Reads and refreshes one admin dashboard block through the cache gateway.
 *
 * Every dashboard endpoint composes its own aggregate query and hands it here,
 * so the cache read, the `fresh=1` bypass, the stored generation timestamp and
 * the response metadata are decided in one place instead of five.
 *
 * The stored entry is `['generated_at' => <ISO 8601>, 'payload' => <array>]`.
 * `generated_at` is persisted beside the payload so a cached response reports
 * when the numbers were generated, not when they were served.
 */
final class DashboardBlockCache
{
    private const string TIMEZONE = 'Asia/Tehran';

    private const string CURRENCY = 'IRR';

    public function __construct(private readonly CacheStore $cache) {}

    /**
     * Return the cached payload, or compute and store it when absent or `fresh`.
     *
     * @template TPayload
     *
     * @param  array<string, scalar|null>  $params
     * @param  Closure(): TPayload  $compute
     * @return array{
     *     payload: TPayload,
     *     metadata: array{
     *         generated_at: string,
     *         cached: bool,
     *         cache_ttl: int,
     *         timezone: string,
     *         currency: string,
     *     },
     * }
     */
    public function remember(CacheKey $key, array $params, Closure $compute, bool $fresh = false): array
    {
        if (! $fresh) {
            $entry = $this->cache->get($key, $params);

            if ($this->isStoredEntry($entry)) {
                return [
                    'payload'  => $entry['payload'],
                    'metadata' => $this->metadata($key, $entry['generated_at'], true),
                ];
            }
        }

        $payload = $compute();

        $entry = [
            'generated_at' => CarbonImmutable::now(self::TIMEZONE)->toIso8601String(),
            'payload'      => $payload,
        ];

        $this->cache->put($key, $params, $entry);

        return [
            'payload'  => $payload,
            'metadata' => $this->metadata($key, $entry['generated_at'], false),
        ];
    }

    /**
     * The response metadata every dashboard block carries.
     *
     * @return array{
     *     generated_at: string,
     *     cached: bool,
     *     cache_ttl: int,
     *     timezone: string,
     *     currency: string,
     * }
     */
    private function metadata(CacheKey $key, string $generatedAt, bool $cached): array
    {
        return [
            'generated_at' => Verta::instance(CarbonImmutable::parse($generatedAt))->format('Y-m-d H:i:s'),
            'cached'       => $cached,
            'cache_ttl'    => (int) $key->ttl(),
            'timezone'     => self::TIMEZONE,
            'currency'     => self::CURRENCY,
        ];
    }

    /**
     * Whether a cache read returned the entry shape this service writes.
     */
    private function isStoredEntry(mixed $entry): bool
    {
        return is_array($entry)
            && is_string($entry['generated_at'] ?? null)
            && array_key_exists('payload', $entry);
    }
}
