<?php

declare(strict_types=1);

namespace App\Enums\Content;

use App\Enums\System\CacheKeysEnum;

enum PartnerShowInEnum: string
{
    case HOME   = 'home';
    case COURSE = 'course';

    public static function getCacheKey(?string $value): CacheKeysEnum
    {
        return match ($value) {
            self::HOME->value   => CacheKeysEnum::PartnersInHome,
            self::COURSE->value => CacheKeysEnum::PartnersInCourse,
            default             => CacheKeysEnum::Partners,
        };
    }
}
