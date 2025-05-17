<?php

namespace App\Enums;

use App\Traits\AdvanceEnum;

enum CourseStatusEnum: string
{
    use AdvanceEnum;
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

}
