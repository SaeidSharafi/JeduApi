<?php

declare(strict_types=1);

namespace App\Enums;

enum PartnerShowInEnum: string
{
    case HOME = 'home';
    case COURSE = 'course';

    /**
     * Map a partner "show in" value to its corresponding cache key enum.
     *
     * Returns CacheKeysEnum::PartnersInHome when $value is 'home', CacheKeysEnum::PartnersInCourse when $value is 'course',
     * and CacheKeysEnum::Partners for any other or null value.
     *
     * @param string|null $value The enum-backed string value (e.g., 'home' or 'course').
     * @return CacheKeysEnum The cache key enum corresponding to the provided value.
     */
    public static function getCacheKey(?string $value): CacheKeysEnum
    {
       return match ($value) {
            self::HOME->value => CacheKeysEnum::PartnersInHome,
            self::COURSE->value => CacheKeysEnum::PartnersInCourse,
            default => CacheKeysEnum::Partners,
        };
    }
}
