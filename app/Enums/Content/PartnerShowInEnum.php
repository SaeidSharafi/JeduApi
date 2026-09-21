<?php

declare(strict_types=1);

namespace App\Enums\Content;

use App\Enums\System\CacheKey;

enum PartnerShowInEnum: string
{
    case HOME   = 'home';
    case COURSE = 'course';

    public static function getCacheKey(?string $value): CacheKey
    {
        return match ($value) {
            self::HOME->value   => CacheKey::PartnersInHome,
            self::COURSE->value => CacheKey::PartnersInCourse,
            default             => CacheKey::Partners,
        };
    }
}
