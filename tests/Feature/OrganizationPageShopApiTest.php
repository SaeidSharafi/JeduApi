<?php

declare(strict_types=1);

use App\Actions\Admin\Vendor\UpdateVendorAction;
use App\Contracts\Cache\CacheStore;
use App\Data\Admin\Vendor\CreateVendorData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\System\CacheKey;
use App\Http\Controllers\Api\Shop\OrganizationPageController;
use App\Models\Course;
use App\Models\OrganizationPage;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Seminar;
use App\Models\Vendor;

mutates(OrganizationPageController::class);

it('returns page content and no courses when no vendor is configured', function (): void {
    $response = $this->getJson(route('api.v1.shop.organization.show'));

    $response->assertOk()
        ->assertJsonPath('data.vendor', null)
        ->assertJsonPath('data.hero_image_url', null)
        ->assertJsonPath('data.educational_calendar_url', null)
        ->assertJsonPath('data.recent_courses', [])
        ->assertJsonMissingPath('data.media');
});

it('returns six recent published courses with commercial data only for products in the default course listing', function (): void {
    $vendor      = Vendor::factory()->create();
    $otherVendor = Vendor::factory()->create();
    OrganizationPage::query()->firstOrFail()->update(['vendor_id' => $vendor->id]);

    $createCourseProduct = function (
        Vendor $productVendor,
        string $name,
        array $courseAttributes = [],
        array $productAttributes = [],
        array $deliveryAttributes = [],
    ): Course {
        $course = Course::factory()->create([
            'full_name' => $name,
            ...$courseAttributes,
        ]);
        $product = Product::factory()->withCourse($course)->create([
            'vendor_id' => $productVendor->id,
            'name'      => 'Product '.$name,
            ...$productAttributes,
        ]);
        ProductDeliveryOption::factory()->create([
            'product_id' => $product->id,
            'price'      => 100000,
            ...$deliveryAttributes,
        ]);

        return $course;
    };

    foreach (range(1, 8) as $number) {
        $createCourseProduct($vendor, 'Course '.$number, [
            'created_at' => now()->subMinutes(100 - $number),
        ]);
    }

    $futureCourse = $createCourseProduct($vendor, 'Future course', [
        'created_at' => now()->addMinutes(2),
    ], [], [
        'price'          => 100000,
        'available_from' => now()->addDay(),
    ]);
    $expiredCourse = $createCourseProduct($vendor, 'Expired course', [
        'created_at' => now()->addMinute(),
    ], [], [
        'available_to' => now()->subDay(),
    ]);

    $draftCourse = $createCourseProduct($vendor, 'Draft course', [
        'created_at' => now()->addMinutes(4),
    ], ['status' => PublicationStatusEnum::DRAFT]);
    $hiddenCourse = $createCourseProduct($vendor, 'Hidden course', [
        'created_at' => now()->addMinutes(3),
    ], ['is_visible' => false]);

    $createCourseProduct($otherVendor, 'Other department course');

    $seminar        = Seminar::factory()->create();
    $seminarProduct = Product::factory()->withSeminar($seminar)->create([
        'vendor_id' => $vendor->id,
        'name'      => 'Department seminar',
    ]);
    ProductDeliveryOption::factory()->create(['product_id' => $seminarProduct->id]);

    $response = $this->getJson(route('api.v1.shop.organization.show'));

    $response->assertOk()
        ->assertJsonPath('data.vendor.name', $vendor->name)
        ->assertJsonMissingPath('data.vendor_id')
        ->assertJsonMissingPath('data.vendor.id')
        ->assertJsonCount(6, 'data.recent_courses')
        ->assertJsonPath('data.recent_courses.0.name', $draftCourse->full_name)
        ->assertJsonPath('data.recent_courses.1.name', $hiddenCourse->full_name)
        ->assertJsonPath('data.recent_courses.2.name', 'Future course')
        ->assertJsonPath('data.recent_courses.3.name', 'Expired course')
        ->assertJsonPath('data.recent_courses.0.slug', $draftCourse->slug)
        ->assertJsonPath('data.recent_courses.2.slug', $futureCourse->slug)
        ->assertJsonPath('data.recent_courses.3.slug', $expiredCourse->slug)
        ->assertJsonPath('data.recent_courses.2.price', null)
        ->assertJsonPath('data.recent_courses.3.price', null)
        ->assertJsonPath('data.recent_courses.4.price', 100000)
        ->assertJsonMissingPath('data.recent_courses.0.course_info')
        ->assertJsonMissingPath('data.recent_courses.0.product_info');

    expect($response->json('data.recent_courses.0.price'))
        ->toBeNull()
        ->and($response->json('data.recent_courses.0.is_free'))->toBeFalse()
        ->and($response->json('data.recent_courses.2.is_free'))->toBeFalse();

    expect($response->json('data.recent_courses.*.name'))
        ->toContain('Future course', 'Expired course', 'Draft course', 'Hidden course')
        ->not->toContain('Other department course', 'Department seminar');
});

it('keeps courses visible when their only product is archived', function (): void {
    $vendor = Vendor::factory()->create();
    OrganizationPage::query()->singleton()->firstOrFail()->update(['vendor_id' => $vendor->id]);
    $course  = Course::factory()->create();
    $product = Product::factory()->withCourse($course)->create([
        'vendor_id' => $vendor->id,
        'status'    => PublicationStatusEnum::ARCHIVED,
    ]);
    ProductDeliveryOption::factory()->create(['product_id' => $product->id, 'price' => 100000]);

    $response = $this->getJson(route('api.v1.shop.organization.show'));

    $response->assertOk()
        ->assertJsonCount(1, 'data.recent_courses')
        ->assertJsonPath('data.recent_courses.0.name', $course->full_name)
        ->assertJsonPath('data.recent_courses.0.slug', $course->slug)
        ->assertJsonPath('data.recent_courses.0.price', null)
        ->assertJsonPath('data.recent_courses.0.price_data', null);
});

it('keeps completed courses visible without commercial data from ended products', function (): void {
    $vendor = Vendor::factory()->create();
    OrganizationPage::query()->singleton()->firstOrFail()->update(['vendor_id' => $vendor->id]);
    $course  = Course::factory()->create();
    $product = Product::factory()->withCourse($course)->create([
        'vendor_id'      => $vendor->id,
        'event_ended_at' => today()->subDay(),
    ]);
    ProductDeliveryOption::factory()->create(['product_id' => $product->id, 'price' => 100000]);

    $response = $this->getJson(route('api.v1.shop.organization.show'));

    $response->assertOk()
        ->assertJsonCount(1, 'data.recent_courses')
        ->assertJsonPath('data.recent_courses.0.slug', $course->slug)
        ->assertJsonPath('data.recent_courses.0.price', null)
        ->assertJsonPath('data.recent_courses.0.price_data', null)
        ->assertJsonPath('data.recent_courses.0.is_free', false);
});

it('shows commercial data for listed products after registration closes', function (): void {
    $vendor = Vendor::factory()->create();
    OrganizationPage::query()->singleton()->firstOrFail()->update(['vendor_id' => $vendor->id]);
    $course  = Course::factory()->create();
    $product = Product::factory()->withCourse($course)->create(['vendor_id' => $vendor->id]);
    ProductDeliveryOption::factory()->create([
        'product_id'            => $product->id,
        'price'                 => 100000,
        'registration_end_date' => today()->subDay(),
    ]);

    $response = $this->getJson(route('api.v1.shop.organization.show'));

    $response->assertOk()
        ->assertJsonPath('data.recent_courses.0.slug', $product->slug)
        ->assertJsonPath('data.recent_courses.0.price', 100000);
});

it('accepts a recent-course limit of twenty and rejects larger limits', function (): void {
    $vendor = Vendor::factory()->create();
    OrganizationPage::query()->firstOrFail()->update(['vendor_id' => $vendor->id]);

    foreach (range(1, 20) as $number) {
        $course  = Course::factory()->create();
        $product = Product::factory()->withCourse($course)->create([
            'vendor_id' => $vendor->id,
            'name'      => 'Course '.$number,
        ]);
        ProductDeliveryOption::factory()->create(['product_id' => $product->id]);
    }

    $maximumResponse = $this->getJson(route('api.v1.shop.organization.show', ['limit' => 20]));

    $maximumResponse->assertOk()->assertJsonCount(20, 'data.recent_courses');

    $tooLargeResponse = $this->getJson(route('api.v1.shop.organization.show', ['limit' => 21]));

    $tooLargeResponse->assertUnprocessable()
        ->assertJsonValidationErrors(['limit']);
});

it('exposes configured content, faq visibility, and public media urls', function (): void {
    $vendor = Vendor::factory()->create();
    $page   = OrganizationPage::query()->firstOrFail();
    $page->update([
        'vendor_id'                   => $vendor->id,
        'hero_title'                  => 'Organization services',
        'hero_description'            => 'Department description',
        'ims_portal_url'              => 'https://ims.example.test/organizations',
        'request_section_title'       => 'Request training',
        'request_section_explanation' => 'Tell us what your organization needs.',
        'hero_image_url'              => 'https://cdn.example.test/organization-hero.jpg',
        'educational_calendar_url'    => 'https://cdn.example.test/educational-calendar.pdf',
        'faqs'                        => [[
            'question'   => 'What can we request?',
            'answer'     => 'A custom training plan.',
            'is_visible' => true,
        ], [
            'question'   => 'Hidden question',
            'answer'     => 'Hidden answer',
            'is_visible' => false,
        ]],
    ]);
    $response = $this->getJson(route('api.v1.shop.organization.show', ['limit' => 1]));

    $response->assertOk()
        ->assertJsonPath('data.hero_title', 'Organization services')
        ->assertJsonPath('data.ims_portal_url', 'https://ims.example.test/organizations')
        ->assertJsonPath('data.hero_image_url', 'https://cdn.example.test/organization-hero.jpg')
        ->assertJsonPath('data.educational_calendar_url', 'https://cdn.example.test/educational-calendar.pdf')
        ->assertJsonPath('data.faqs.1.is_visible', false)
        ->assertJsonMissingPath('data.media');
});

it('caches the public page projection by requested course limit', function (): void {
    app(CacheStore::class)->forget(CacheKey::OrganizationPage, ['limit' => 6]);
    $page = OrganizationPage::query()->firstOrFail();
    $page->update(['hero_title' => 'Cached title']);

    $firstResponse = $this->getJson(route('api.v1.shop.organization.show'));

    $firstResponse->assertOk()->assertJsonPath('data.hero_title', 'Cached title');

    $page->update(['hero_title' => 'Database title']);

    $secondResponse = $this->getJson(route('api.v1.shop.organization.show'));

    $secondResponse->assertOk()->assertJsonPath('data.hero_title', 'Cached title');
});

it('invalidates the cached page when its linked vendor is updated', function (): void {
    $vendor = Vendor::factory()->create(['name' => 'Original department']);
    OrganizationPage::query()->singleton()->firstOrFail()->update(['vendor_id' => $vendor->id]);
    app(CacheStore::class)->forget(CacheKey::OrganizationPage, ['limit' => 6]);

    $this->getJson(route('api.v1.shop.organization.show'))
        ->assertOk()
        ->assertJsonPath('data.vendor.name', 'Original department');

    app(UpdateVendorAction::class)->handle(new CreateVendorData(
        name: 'Updated department',
        email: null,
        phone: null,
        phone2: null,
        address: null,
        map_location: null,
        social_links: null,
        theme_options: null,
        media: ['logo' => null, 'favicon' => null],
    ), $vendor);

    $this->getJson(route('api.v1.shop.organization.show'))
        ->assertOk()
        ->assertJsonPath('data.vendor.name', 'Updated department');
});
