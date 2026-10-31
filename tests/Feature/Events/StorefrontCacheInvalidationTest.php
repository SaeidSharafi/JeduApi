<?php

declare(strict_types=1);

use App\Actions\Admin\Category\UpdateCategoryAction;
use App\Actions\Admin\Course\UpdateCourseAction;
use App\Actions\Admin\Slider\UpdateSliderAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Category\CreateCategoryData;
use App\Data\Admin\Course\CreateCourseData;
use App\Data\Admin\Slider\SliderCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\System\SettingKeyEnum;
use App\Enums\System\StorefrontTag;
use App\Events\StorefrontCacheInvalidated;
use App\Jobs\UpdateProductAvailabilityJob;
use App\Jobs\UpdateProductPricingJob;
use App\Models\Blog\BlogPost;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Slider;
use App\Models\Term;
use App\Services\BundleAvailabilityService;
use App\Services\ProductPriceService;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Plank\Mediable\Facades\MediaUploader;

/**
 * Pins the storefront tags each write path announces.
 *
 * Deliberately no `mutates()`: the file exercises many actions, and each of them is
 * a mutation target in its own test file. What is under test here is the *decision*
 * — which tags a given write disturbs — because an unknown tag makes the storefront
 * refuse the whole payload, and a missing tag leaves content stale until its TTL.
 */
function assertStorefrontRevalidationTags(array $expected): void
{
    Event::assertDispatched(
        StorefrontCacheInvalidated::class,
        fn (StorefrontCacheInvalidated $event): bool => $event->tags === $expected,
    );
}

function courseWriteData(string $slug): CreateCourseData
{
    return CreateCourseData::from([
        'slug'                    => $slug,
        'full_name'               => 'Course '.$slug,
        'short_name'              => 'C',
        'description'             => null,
        'duration'                => null,
        'difficulty_level'        => CourseDifficultyLevelEnum::BEGINNER->value,
        'career_prospects_text'   => null,
        'curriculum_summary_text' => null,
        'outcomes_json'           => [],
        'default_teacher_info'    => null,
        'provides_certificate'    => false,
        'faq'                     => null,
        'additional_info'         => null,
        'meta_title'              => null,
        'meta_description'        => null,
        'meta_keywords'           => null,
        'properties'              => null,
        'status'                  => PublicationStatusEnum::PUBLISHED->value,
        'categories'              => [],
        'digital_assets'          => [],
        'media'                   => [],
    ]);
}

function categoryWriteData(string $slug): CreateCategoryData
{
    return CreateCategoryData::from([
        'name'             => 'Category '.$slug,
        'slug'             => $slug,
        'status'           => PublicationStatusEnum::PUBLISHED->value,
        'parent_id'        => null,
        'description'      => null,
        'color_scheme'     => null,
        'meta_title'       => 'Meta title',
        'meta_description' => 'Meta description that is long enough to satisfy the validation rule of the DTO.',
        'meta_keywords'    => null,
        'properties'       => null,
        'additional_info'  => null,
        'media'            => [],
    ]);
}

beforeEach(function (): void {
    Event::fake([StorefrontCacheInvalidated::class]);
    Storage::fake('public');
});

describe('storefront revalidation from content writes', function (): void {
    it('clears all four catalogue collections, the category tree and home blocks on a course write', function (): void {
        $course = Course::factory()->create();

        app(UpdateCourseAction::class)->handle(courseWriteData('course-b'), $course);

        assertStorefrontRevalidationTags([
            ...StorefrontTag::catalogWith(StorefrontTag::CatalogCategories, StorefrontTag::HomeBlocks),
        ]);
    });

    it('clears the category tree, the header menu, the footer and home blocks on a category write', function (): void {
        $category = Category::factory()->create();

        app(UpdateCategoryAction::class)->handle(categoryWriteData('category-b'), $category);

        assertStorefrontRevalidationTags([
            ...StorefrontTag::catalogWith(
                StorefrontTag::CatalogCategories,
                StorefrontTag::SettingsCategories,
                StorefrontTag::SettingsFooter,
                StorefrontTag::HomeBlocks,
            ),
        ]);
    });

    it('clears the home slider tag on a slider write', function (): void {
        $slider = Slider::factory()->create();
        $data   = SliderCreateData::from([
            'title'   => 'Updated Slider',
            'caption' => null,
            'status'  => PublicationStatusEnum::PUBLISHED->value,
            'image'   => MediaUploader::fromSource(UploadedFile::fake()->image('slide.jpg'))
                ->toDisk('public')
                ->upload()
                ->id,
            'link'  => null,
            'order' => 2,
        ]);

        app(UpdateSliderAction::class)->handle($slider, $data);

        assertStorefrontRevalidationTags([StorefrontTag::HomeSliders]);
    });

    it('clears the blog and home block tags when a scheduled post is published', function (): void {
        BlogPost::factory()->create([
            'status'       => PublicationStatusEnum::SCHEDULED,
            'published_at' => now()->subDay(),
        ]);

        $this->artisan('post:publish')->assertExitCode(0);

        assertStorefrontRevalidationTags([StorefrontTag::BlogPosts, StorefrontTag::HomeBlocks]);
    });
});

describe('storefront revalidation from settings writes', function (): void {
    it('clears the matching storefront tag for a public setting', function (SettingKeyEnum $key, StorefrontTag $tag): void {
        app(SettingsService::class)->set($key, ['value'], 'json', 'site');

        assertStorefrontRevalidationTags([$tag]);
    })->with([
        'header'   => [SettingKeyEnum::HEADER, StorefrontTag::SettingsHeader],
        'footer'   => [SettingKeyEnum::FOOTER, StorefrontTag::SettingsFooter],
        'identity' => [SettingKeyEnum::SITE_IDENTITY, StorefrontTag::SettingsSite],
        'about us' => [SettingKeyEnum::ABOUT_US, StorefrontTag::CmsAboutUs],
        'contact'  => [SettingKeyEnum::CONTACT_INFO, StorefrontTag::CmsContactUs],
        'collab'   => [SettingKeyEnum::COLLABORATION, StorefrontTag::CmsCollaboration],
    ]);

    it('notifies nobody for a setting the storefront does not render', function (): void {
        app(SettingsService::class)->set(SettingKeyEnum::SEARCH_ENGINE, 'pgroonga', 'string', 'system');

        Event::assertNotDispatched(StorefrontCacheInvalidated::class);
    });
});

describe('storefront revalidation from the commerce jobs', function (): void {
    it('clears the catalogue after recalculating prices', function (): void {
        $product = Product::factory()->create();

        (new UpdateProductPricingJob([$product->id]))->handle(app(ProductPriceService::class));

        assertStorefrontRevalidationTags([...StorefrontTag::catalogWith(StorefrontTag::HomeBlocks)]);
    });

    it('clears the catalogue after recalculating availability', function (): void {
        $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $term    = Term::factory()->create(['status' => 'active']);
        $product = Product::factory()->withCourse($course)->create([
            'term_id'    => $term->id,
            'is_visible' => true,
            'status'     => PublicationStatusEnum::PUBLISHED,
        ]);
        ProductDeliveryOption::factory()->create([
            'product_id'     => $product->id,
            'status'         => PublicationStatusEnum::PUBLISHED,
            'available_from' => now()->subDay()->toDateString(),
            'available_to'   => now()->addMonth()->toDateString(),
        ]);

        (new UpdateProductAvailabilityJob([$product->id]))->handle(
            app(CacheStore::class),
            app(BundleAvailabilityService::class),
        );

        assertStorefrontRevalidationTags([...StorefrontTag::catalogWith(StorefrontTag::HomeBlocks)]);
    });
});
