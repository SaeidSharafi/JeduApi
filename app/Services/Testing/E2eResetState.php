<?php

declare(strict_types=1);

namespace App\Services\Testing;

use Illuminate\Support\Facades\Redis;

final class E2eResetState
{
    private const RESETTING_KEY = 'e2e:reset:active';

    private const ACTIVE_JOB_PREFIX = 'e2e:reset:active-job:';

    private const WORKER_HEARTBEAT_PREFIX = 'e2e:worker:heartbeat:';

    public function begin(string $resetId, int $ttlSeconds): void
    {
        $this->redis()->setex(self::RESETTING_KEY, $ttlSeconds, $resetId);
    }

    public function finish(): void
    {
        $this->redis()->del(self::RESETTING_KEY);
    }

    public function isResetting(): bool
    {
        return $this->redis()->exists(self::RESETTING_KEY) === 1;
    }

    public function markJobStarted(string $jobId, int $ttlSeconds): void
    {
        $this->redis()->setex(self::ACTIVE_JOB_PREFIX.$jobId, $ttlSeconds, '1');
    }

    public function markJobFinished(string $jobId): void
    {
        $this->redis()->del(self::ACTIVE_JOB_PREFIX.$jobId);
    }

    public function activeJobCount(): int
    {
        return count($this->redis()->keys(self::ACTIVE_JOB_PREFIX.'*'));
    }

    public function markWorkerReady(string $workerId, int $ttlSeconds): void
    {
        $this->redis()->setex(self::WORKER_HEARTBEAT_PREFIX.$workerId, $ttlSeconds, (string) time());
    }

    public function hasReadyWorker(): bool
    {
        return $this->redis()->keys(self::WORKER_HEARTBEAT_PREFIX.'*') !== [];
    }

    public function clearWorkerHeartbeats(): void
    {
        $keys = $this->redis()->keys(self::WORKER_HEARTBEAT_PREFIX.'*');

        if ($keys !== []) {
            $this->redis()->del($keys);
        }
    }

    private function redis(): mixed
    {
        return Redis::connection('e2e_lock');
    }
}
