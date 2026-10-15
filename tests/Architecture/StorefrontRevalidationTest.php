<?php

declare(strict_types=1);

use App\Enums\System\StorefrontTag;

/**
 * The storefront's allowlist, copied verbatim from REVALIDATE_TAGS in
 * `/home/kuro/www/JeduFront/shop/src/lib/revalidateRequest.js` (see
 * `docs/caching-plan.md` §5 there). An unlisted tag makes the storefront answer
 * `400` and clear nothing, so the copy is the drift alarm: if the storefront
 * widens or narrows its list, this test fails until the enum is updated.
 *
 * @return list<string>
 */
function storefrontAllowlist(): array
{
    return [
        'settings:header',
        'settings:footer',
        'settings:categories',
        'home:sliders',
        'home:partners',
        'home:student-stories',
        'home:blocks',
        'catalog:categories',
        'catalog:courses',
        'catalog:books',
        'catalog:seminars',
        'catalog:bundles',
        'catalog:learning-paths',
        'blog:posts',
        'blog:categories',
        'cms:about-us',
        'cms:contact-us',
        'cms:collaboration',
    ];
}

it('matches the tags the storefront accepts', function (): void {
    $expected = storefrontAllowlist();
    $actual   = StorefrontTag::values();

    sort($expected);
    sort($actual);

    expect($actual)->toBe($expected);
});

it('clears every catalogue collection through the coarse catalog helper', function (): void {
    expect(StorefrontTag::catalog())->toBe([
        StorefrontTag::CatalogCourses,
        StorefrontTag::CatalogBooks,
        StorefrontTag::CatalogSeminars,
        StorefrontTag::CatalogBundles,
    ]);
});

/**
 * Every write path that changes public storefront content, and the tags it must
 * announce. This is the executable form of the mapping in
 * `.ai/rules/storefront-cache.md`: adding a storefront resource without adding its
 * write path here — or wiring a path without its tags — fails the suite.
 *
 * `catalog()` and `catalogWith()` are expanded to the four collection tags so the
 * expectation matches the literal constants in the file.
 *
 * @return array<string, list<string>>
 */
function storefrontDispatchInventory(): array
{
    $catalog  = ['CatalogCourses', 'CatalogBooks', 'CatalogSeminars', 'CatalogBundles'];
    $content  = [...$catalog, 'CatalogCategories', 'HomeBlocks'];
    $category = [...$content, 'SettingsCategories', 'SettingsFooter'];
    $blogPost = ['BlogPosts', 'HomeBlocks'];
    $blogCat  = ['BlogCategories', 'BlogPosts', 'HomeBlocks'];
    $learning = ['CatalogLearningPaths'];
    $settings = ['SettingsHeader', 'SettingsFooter', 'CmsAboutUs', 'CmsContactUs', 'CmsCollaboration'];

    $catalogMedia = [
        'app/Actions/Admin/Course/CreateCourseAction.php',
        'app/Actions/Admin/Course/UpdateCourseAction.php',
        'app/Actions/Admin/Course/DeleteCourseAction.php',
        'app/Actions/Admin/DigitalAsset/CreateDigitalAssetAction.php',
        'app/Actions/Admin/DigitalAsset/UpdateDigitalAssetAction.php',
        'app/Actions/Admin/DigitalAsset/DeleteDigitalAssetAction.php',
        'app/Actions/Admin/Seminar/CreateSeminarAction.php',
        'app/Actions/Admin/Seminar/UpdateSeminarAction.php',
        'app/Actions/Admin/Seminar/DeleteSeminarAction.php',
    ];

    $inventory = [];

    foreach ($catalogMedia as $file) {
        $inventory[$file] = $content;
    }

    foreach ([
        'app/Actions/Admin/Bundle/CreateBundleAction.php',
        'app/Actions/Admin/Bundle/UpdateBundleAction.php',
        'app/Actions/Admin/Bundle/DeleteBundleAction.php',
    ] as $file) {
        $inventory[$file] = $catalog;
    }

    foreach ([
        'app/Actions/Admin/LearningPath/CreateLearningPathAction.php',
        'app/Actions/Admin/LearningPath/UpdateLearningPathAction.php',
        'app/Actions/Admin/LearningPath/DeleteLearningPathAction.php',
        'app/Actions/Admin/LearningPath/ArchiveLearningPathAction.php',
    ] as $file) {
        $inventory[$file] = $learning;
    }

    foreach ([
        'app/Actions/Admin/Category/CreateCategoryAction.php',
        'app/Actions/Admin/Category/UpdateCategoryAction.php',
        'app/Actions/Admin/Category/DeleteCategoryAction.php',
    ] as $file) {
        $inventory[$file] = $category;
    }

    $inventory['app/Actions/Admin/Category/SetGoodForStartAction.php'] = [...$catalog, 'HomeBlocks'];

    foreach ([
        'app/Actions/Admin/Slider/CreateSliderAction.php',
        'app/Actions/Admin/Slider/UpdateSliderAction.php',
        'app/Actions/Admin/Slider/UpdateSliderStatusAction.php',
        'app/Actions/Admin/Slider/DeleteSliderAction.php',
    ] as $file) {
        $inventory[$file] = ['HomeSliders'];
    }

    foreach ([
        'app/Actions/Admin/Setting/StoreHomePageBlockAction.php',
        'app/Actions/Admin/Setting/UpdateHomePageBlockAction.php',
        'app/Actions/Admin/Setting/DeleteHomePageBlockAction.php',
    ] as $file) {
        $inventory[$file] = ['HomeBlocks'];
    }

    $inventory['app/Actions/Admin/Partner/ForgetPartnerCachesAction.php'] = ['HomePartners'];

    foreach ([
        'app/Actions/Admin/Setting/StudentStory/CreateStudentStoryAction.php',
        'app/Actions/Admin/Setting/StudentStory/UpdateStudentStoryAction.php',
        'app/Actions/Admin/Setting/StudentStory/DeleteStudentStoryAction.php',
    ] as $file) {
        $inventory[$file] = ['HomeStudentStories'];
    }

    foreach ([
        'app/Actions/Admin/Blog/Post/CreateBlogPostAction.php',
        'app/Actions/Admin/Blog/Post/UpdateBlogPostAction.php',
        'app/Actions/Admin/Blog/Post/DeleteBlogPostAction.php',
        'app/Console/Commands/PublishPostCommand.php',
    ] as $file) {
        $inventory[$file] = $blogPost;
    }

    foreach ([
        'app/Actions/Admin/Blog/Category/CreateBlogCategoryAction.php',
        'app/Actions/Admin/Blog/Category/UpdateBlogCategoryAction.php',
        'app/Actions/Admin/Blog/Category/DeleteBlogCategoryAction.php',
    ] as $file) {
        $inventory[$file] = $blogCat;
    }

    foreach ([
        'app/Actions/Admin/Review/UpdateReviewFeaturedStatusAction.php',
        'app/Listeners/RecalculateReviewableAggregates.php',
    ] as $file) {
        $inventory[$file] = [...$catalog, 'BlogPosts'];
    }

    foreach ([
        'app/Actions/Admin/Teacher/CreateTeacherAction.php',
        'app/Actions/Admin/Teacher/UpdateTeacherAction.php',
        'app/Actions/Admin/Teacher/DeleteTeacherAction.php',
        'app/Actions/Admin/Term/CreateTermAction.php',
        'app/Actions/Admin/Term/UpdateTermAction.php',
        'app/Actions/Admin/Term/DeleteTermAction.php',
        'app/Jobs/UpdateProductPricingJob.php',
        'app/Jobs/UpdateProductAvailabilityJob.php',
    ] as $file) {
        $inventory[$file] = [...$catalog, 'HomeBlocks'];
    }

    // Settings dispatch once from SettingsService; the tag for each key is declared
    // in SettingKeyEnum, which `it('maps every public setting to its storefront tag')`
    // pins separately, so no tag is asserted literally in the service.
    $inventory['app/Services/SettingsService.php'] = [];

    return $inventory;
}

/**
 * The four tags StorefrontTag::catalog()/catalogWith() expand to.
 *
 * @return list<string>
 */
function storefrontCollectionCases(): array
{
    return ['CatalogCourses', 'CatalogBooks', 'CatalogSeminars', 'CatalogBundles'];
}

function storefrontSettingsMapTags(): array
{
    return ['SettingsHeader', 'SettingsFooter', 'CmsAboutUs', 'CmsContactUs', 'CmsCollaboration'];
}

it('dispatches from every write path that changes public content', function (): void {
    $violations = [];

    foreach (storefrontDispatchInventory() as $file => $cases) {
        $code = (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);

        if (! str_contains($code, 'StorefrontCacheInvalidated::dispatch')) {
            $violations[] = $file.': no dispatch';

            continue;
        }

        // catalog()/catalogWith() clear the four collections without naming them.
        $usesCatalog = (bool) preg_match('/StorefrontTag::catalog(?:With)?\(/', $code);

        foreach ($cases as $case) {
            $declared = str_contains($code, 'StorefrontTag::'.$case)
                || ($usesCatalog && in_array($case, storefrontCollectionCases(), true));

            if (! $declared) {
                $violations[] = $file.': missing '.$case;
            }
        }
    }

    expect($violations)->toBe([]);
});

it('names every storefront tag from at least one write path', function (): void {
    $declared = [];
    foreach (storefrontDispatchInventory() as $cases) {
        $declared = [...$declared, ...$cases];
    }
    $declared = [...$declared, ...storefrontSettingsMapTags()];

    $expected = array_map(static fn (StorefrontTag $tag): string => $tag->name, StorefrontTag::cases());

    $declared = array_values(array_unique($declared));
    sort($declared);
    sort($expected);

    expect($declared)->toBe($expected);
});

it('maps every public setting to its storefront tag', function (): void {
    $map = (string) file_get_contents(dirname(__DIR__, 2).'/app/Enums/System/SettingKeyEnum.php');

    $violations = [];
    foreach (storefrontSettingsMapTags() as $case) {
        if (! str_contains($map, 'StorefrontTag::'.$case)) {
            $violations[] = 'SettingKeyEnum missing StorefrontTag::'.$case;
        }
    }

    expect($violations)->toBe([]);
});

it('dispatches from the recalculation jobs only after they invalidate the backend cache', function (): void {
    foreach ([
        'app/Jobs/UpdateProductPricingJob.php',
        'app/Jobs/UpdateProductAvailabilityJob.php',
    ] as $file) {
        $code = (string) file_get_contents(dirname(__DIR__, 2).'/'.$file);

        $invalidateAt = mb_strpos($code, 'invalidate(CacheTag::Catalog');
        $dispatchAt   = mb_strpos($code, 'StorefrontCacheInvalidated::dispatch');

        expect($invalidateAt)->not->toBeFalse()
            ->and($dispatchAt)->not->toBeFalse()
            ->and($dispatchAt)->toBeGreaterThan($invalidateAt);
    }
});

it('does not dispatch from writes that cannot affect the storefront', function (): void {
    foreach ([
        'app/Actions/Admin/Organization/UpdateOrganizationPageAction.php',
        'app/Actions/Admin/Vendor/UpdateVendorAction.php',
        'app/Actions/Admin/RelatedProduct/CreateRelatedProductAction.php',
        'app/Actions/Admin/RelatedProduct/DeleteRelatedProductAction.php',
        'app/Console/Commands/Cache/InvalidateCacheGroupCommand.php',
        'app/Services/ImportExport/Resources/UserImportResource.php',
        'app/Http/Controllers/Api/Admin/Review/ReviewController.php',
    ] as $file) {
        expect((string) file_get_contents(dirname(__DIR__, 2).'/'.$file))
            ->not->toContain('StorefrontCacheInvalidated');
    }
});
