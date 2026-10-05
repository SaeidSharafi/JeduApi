<?php

declare(strict_types=1);

namespace App\Enums\ImportExport;

use App\Traits\AdvanceEnum;

enum UserProvisioningProviderEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case MOODLE   = 'moodle';
    case IMS      = 'ims';
    case NILIROOM = 'niliroom';
    case SKYROOM  = 'skyroom';
}
