<?php

declare(strict_types=1);

namespace App\Enums\ImportExport;

use App\Traits\AdvanceEnum;

enum ProviderOutcomeStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case QUEUED           = 'queued';
    case PROCESSING       = 'processing';
    case SUCCEEDED        = 'succeeded';
    case FAILED           = 'failed';
    case RETRYABLE_FAILED = 'retryable_failed';

    public function isPending(): bool
    {
        return $this === self::QUEUED || $this === self::PROCESSING;
    }

    public function isFailure(): bool
    {
        return $this === self::FAILED || $this === self::RETRYABLE_FAILED;
    }
}
