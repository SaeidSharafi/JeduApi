<?php

declare(strict_types=1);

namespace App\Enums\System;

/**
 * The storefront's cache revalidation vocabulary.
 *
 * The storefront caches its public shop responses in Next's Data Cache under
 * these tags and exposes a single webhook (`POST {storefront}/api/revalidate`)
 * that clears them; the accepted list is an allowlist there, and a tag outside
 * it fails the whole request with `400` instead of clearing nothing quietly.
 * Keeping the list as an enum makes such a tag unrepresentable from this side.
 *
 * Sits beside {@see CacheTag}, which is this application's own invalidation
 * vocabulary: the two are deliberately separate, because the backend cache and
 * the storefront cache are different systems with different lifetimes.
 */
enum StorefrontTag: string
{
    case SettingsHeader     = 'settings:header';
    case SettingsFooter     = 'settings:footer';
    case SettingsCategories = 'settings:categories';
    case SettingsSite       = 'settings:site';

    case HomeSliders        = 'home:sliders';
    case HomePartners       = 'home:partners';
    case HomeStudentStories = 'home:student-stories';
    case HomeBlocks         = 'home:blocks';

    case CatalogCategories    = 'catalog:categories';
    case CatalogCourses       = 'catalog:courses';
    case CatalogBooks         = 'catalog:books';
    case CatalogSeminars      = 'catalog:seminars';
    case CatalogBundles       = 'catalog:bundles';
    case CatalogLearningPaths = 'catalog:learning-paths';

    case BlogPosts      = 'blog:posts';
    case BlogCategories = 'blog:categories';

    case CmsAboutUs       = 'cms:about-us';
    case CmsContactUs     = 'cms:contact-us';
    case CmsCollaboration = 'cms:collaboration';

    /**
     * The four catalogue collection tags, cleared together.
     *
     * Clearing is deliberately coarse: the storefront's tags are collection
     * level, and resolving which collection a product change touched buys a
     * re-fetch of collections that did not change instead of a class of bugs
     * where one was missed.
     *
     * @return list<self>
     */
    public static function catalog(): array
    {
        return [
            self::CatalogCourses,
            self::CatalogBooks,
            self::CatalogSeminars,
            self::CatalogBundles,
        ];
    }

    /**
     * The catalogue collection tags plus the extra tags a specific write disturbs.
     *
     * PHP refuses positional arguments after argument unpacking, so call sites that
     * need more than the four collections reach for this instead.
     *
     * @return list<self>
     */
    public static function catalogWith(self ...$extra): array
    {
        return [...self::catalog(), ...$extra];
    }

    /**
     * Every tag the storefront accepts, in declaration order.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $tag): string => $tag->value, self::cases());
    }
}
