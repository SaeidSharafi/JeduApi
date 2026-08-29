<?php

declare(strict_types=1);

return [
    'control_key'             => env('E2E_CONTROL_KEY'),
    'queue'                   => env('E2E_QUEUE', env('REDIS_QUEUE', 'e2e')),
    'media_disk'              => env('E2E_MEDIA_DISK', 'e2e'),
    'reset_lock_seconds'      => (int) env('E2E_RESET_LOCK_SECONDS', 300),
    'reset_state_ttl_seconds' => (int) env('E2E_RESET_STATE_TTL_SECONDS', 900),
    'worker_ready_timeout'    => (int) env('E2E_WORKER_READY_TIMEOUT', 15),
];
