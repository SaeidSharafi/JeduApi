<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Contracts\Cache\CacheStore;
use App\Enums\System\CacheKey;

/**
 * Drops the cached Moodle quiz list of one student.
 *
 * A provisioning change, a revocation and a progress sync all make that list
 * stale; the per-user key is cleared precisely instead of bumping a whole group.
 */
final readonly class ForgetStudentQuizCacheAction
{
    public function __construct(private CacheStore $cache) {}

    public function handle(int $userId): void
    {
        $this->cache->forget(CacheKey::StudentQuizzes, ['userId' => $userId]);
    }
}
