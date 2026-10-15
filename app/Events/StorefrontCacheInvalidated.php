<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\System\StorefrontTag;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A write changed content the storefront caches, so its tags must be cleared.
 *
 * Dispatched explicitly by the write path that performed the *last* backend
 * invalidation for the change: for content edits that is the action itself, for
 * price and availability it is the recalculation job. Dispatch it after the last
 * invalidation, never before, or the storefront will re-fetch from this API
 * while the API's own cache still holds the previous generation and cache the
 * stale response under a fresh TTL.
 */
final class StorefrontCacheInvalidated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @var list<StorefrontTag> */
    public readonly array $tags;

    public function __construct(StorefrontTag ...$tags)
    {
        $this->tags = array_values($tags);
    }
}
