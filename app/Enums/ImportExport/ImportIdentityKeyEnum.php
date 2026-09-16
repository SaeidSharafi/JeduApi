<?php

declare(strict_types=1);

namespace App\Enums\ImportExport;

use App\Traits\AdvanceEnum;

enum ImportIdentityKeyEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case PHONE = 'phone';
    case EMAIL = 'email';
}
