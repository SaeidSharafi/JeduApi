<?php

declare(strict_types=1);

namespace App\Enums\Operators;

use App\Traits\AdvanceEnum;

enum MatchPolicyEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;
    case ANY = 'any';
    case ALL = 'all';
}
