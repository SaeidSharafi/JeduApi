<?php

declare(strict_types=1);

namespace App\Enums\ImportExport;

use App\Traits\AdvanceEnum;

enum ImportRunStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case PREVIEW_READY = 'preview_ready';
}
