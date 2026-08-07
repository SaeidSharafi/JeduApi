<?php

declare(strict_types=1);

namespace App\Enums\Content;

use App\Traits\AdvanceEnum;

enum PublicationStatusEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case DRAFT     = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED  = 'archived';
    case SCHEDULED = 'scheduled';

}
