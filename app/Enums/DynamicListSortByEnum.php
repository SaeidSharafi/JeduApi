<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum DynamicListSortByEnum: string
{
    use AdvanceEnum;

    case CREATED_AT_DESC = 'created_at:desc';
    case CREATED_AT_ASC  = 'created_at:asc';
    case UPDATED_AT_DESC = 'updated_at:desc';
    case UPDATED_AT_ASC  = 'updated_at:asc';
    case NAME_ASC        = 'name:asc';
    case NAME_DESC       = 'name:desc';
    case POPULAR         = 'popular'; // For products: based on order count
    case FEATURED        = 'featured'; // For featured content
}
