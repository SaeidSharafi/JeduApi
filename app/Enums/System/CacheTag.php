<?php

declare(strict_types=1);

namespace App\Enums\System;

/**
 * The invalidation vocabulary for the cache layer.
 *
 * Every {@see CacheKey} declares exactly one tag, and a write path invalidates
 * the tags it disturbed. Group invalidation is a version counter per tag, so
 * bumping a tag makes the previous generation of its keys unreachable without
 * scanning or tagging the underlying store.
 *
 * Deliberately not an {@see \App\Traits\AdvanceEnum} adopter: the tag has no
 * user-facing label to translate, only the values the operator commands list.
 */
enum CacheTag: string
{
    case HomePage  = 'home_page';
    case Content   = 'content';
    case Catalog   = 'catalog';
    case Search    = 'search';
    case Discounts = 'discounts';
    case Settings  = 'settings';
    case Auth      = 'auth';

    /**
     * Every tag value, in declaration order.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $tag): string => $tag->value, self::cases());
    }
}
