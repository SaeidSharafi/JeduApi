<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Storefront\StorefrontRevalidationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes a storefront cache revalidation off the request that caused it.
 *
 * Retried only for conditions a retry can fix (transport failures and 5xx other
 * than the storefront's "not configured" 503); see
 * {@see StorefrontRevalidationService::revalidate()}.
 */
final class RevalidateStorefrontCacheJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 15;

    /**
     * @param  list<string>  $tags
     */
    public function __construct(public array $tags) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(StorefrontRevalidationService $service): void
    {
        $service->revalidate($this->tags);
    }
}
