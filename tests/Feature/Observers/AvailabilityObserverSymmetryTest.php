<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use Illuminate\Support\Facades\Event;

describe('ProductableAvailabilityObserver - symmetric invalidation', function (): void {
    beforeEach(function (): void {
        Event::fake([ProductAvailabilityCacheInvalidated::class, ProductSearchIndexInvalidated::class]);
    });

    it('invalidates availability when a productable transitions TO published (first publish)', function (): void {
        $course  = Course::factory()->create(['status' => PublicationStatusEnum::DRAFT]);
        $product = Product::factory()->create([
            'productable_type' => ProductableEnum::COURSE->value,
            'productable_id'   => $course->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
        ]);

        $course->update(['status' => PublicationStatusEnum::PUBLISHED]);

        Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn ($event): bool => $event->productIds === [$product->id]);
    });

    it('invalidates availability when a productable transitions FROM published (unpublish)', function (): void {
        $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product = Product::factory()->create([
            'productable_type' => ProductableEnum::COURSE->value,
            'productable_id'   => $course->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
        ]);

        $course->update(['status' => PublicationStatusEnum::DRAFT]);

        Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn ($event): bool => $event->productIds === [$product->id]);
    });

    it('does not invalidate availability on unrelated field updates', function (): void {
        $course = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        Product::factory()->create([
            'productable_type' => ProductableEnum::COURSE->value,
            'productable_id'   => $course->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
        ]);

        $course->update(['short_name' => 'Renamed']);

        Event::assertNotDispatched(ProductAvailabilityCacheInvalidated::class);
    });

    it('invalidates search index when a searchable field changes', function (): void {
        $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product = Product::factory()->create([
            'productable_type' => ProductableEnum::COURSE->value,
            'productable_id'   => $course->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
        ]);

        $course->update(['full_name' => 'New Full Name']);

        Event::assertDispatched(ProductSearchIndexInvalidated::class, fn ($event): bool => $event->productIds === [$product->id]);
    });

    it('invalidates availability when a bundle transitions TO published (first publish)', function (): void {
        $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::DRAFT]);
        $product = Product::factory()->create([
            'productable_type' => ProductableEnum::BUNDLE->value,
            'productable_id'   => $bundle->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
        ]);

        $bundle->update(['status' => PublicationStatusEnum::PUBLISHED]);

        Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn ($event): bool => $event->productIds === [$product->id]);
    });

    it('invalidates availability when a bundle transitions FROM published (archive)', function (): void {
        $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
        $product = Product::factory()->create([
            'productable_type' => ProductableEnum::BUNDLE->value,
            'productable_id'   => $bundle->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
        ]);

        $bundle->update(['status' => PublicationStatusEnum::ARCHIVED]);

        Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn ($event): bool => $event->productIds === [$product->id]);
    });
});

describe('TermAvailabilityObserver - symmetric invalidation', function (): void {
    beforeEach(function (): void {
        Event::fake([ProductAvailabilityCacheInvalidated::class]);
    });

    it('invalidates availability when a term becomes ACTIVE', function (): void {
        $term    = Term::factory()->create(['status' => 'inactive']);
        $product = Product::factory()->create([
            'term_id' => $term->id,
            'status'  => PublicationStatusEnum::PUBLISHED,
        ]);

        $term->update(['status' => 'active']);

        Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn ($event): bool => in_array($product->id, $event->productIds, true));
    });

    it('invalidates availability when a term becomes INACTIVE', function (): void {
        $term    = Term::factory()->create(['status' => 'active']);
        $product = Product::factory()->create([
            'term_id' => $term->id,
            'status'  => PublicationStatusEnum::PUBLISHED,
        ]);

        $term->update(['status' => 'inactive']);

        Event::assertDispatched(ProductAvailabilityCacheInvalidated::class, fn ($event): bool => in_array($product->id, $event->productIds, true));
    });

    it('does not invalidate availability on unrelated term updates', function (): void {
        $term = Term::factory()->create(['status' => 'active']);
        Product::factory()->create([
            'term_id' => $term->id,
            'status'  => PublicationStatusEnum::PUBLISHED,
        ]);

        $term->update(['name' => 'Fall 2026']);

        Event::assertNotDispatched(ProductAvailabilityCacheInvalidated::class);
    });
});

describe('ProductableAvailabilityObserver - bundle denormalized snapshot', function (): void {
    it("refreshes a bundle product's snapshot so publishing it puts it in the catalogue", function (): void {
        // The suite forces the flag off (phpunit.xml), so the catalogue would read
        // live relations and the bug this covers would stay invisible.
        config()->set('products.availability.use_denormalized', true);

        $bundle  = Bundle::factory()->create(['status' => PublicationStatusEnum::DRAFT]);
        $product = Product::factory()->create([
            'productable_type' => ProductableEnum::BUNDLE->value,
            'productable_id'   => $bundle->id,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'is_visible'       => true,
            // A stale snapshot: the bundle is draft, so the catalogue must exclude it.
            'productable_status'            => PublicationStatusEnum::DRAFT->value,
            'has_published_delivery_option' => false,
            'is_term_active'                => true,
        ]);
        $componentProduct = Product::factory()->withCourse()->create([
            'is_visible'                    => true,
            'productable_status'            => PublicationStatusEnum::PUBLISHED->value,
            'has_published_delivery_option' => true,
            'is_term_active'                => true,
        ]);
        $component = ProductDeliveryOption::factory()->create([
            'product_id'       => $componentProduct->id,
            'price'            => 100000,
            'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
            'details_json'     => ['moodle_course_id' => 42, 'schedule_days' => ['saturday']],
            // The snapshot flattens component + parent windows, so an open window here
            // keeps the bundle inside the catalogue's available-now window.
            'registration_start_date' => null,
            'registration_end_date'   => null,
            'available_from'          => null,
            'available_to'            => null,
        ]);
        $parentOption = ProductDeliveryOption::factory()->create([
            'product_id'       => $product->id,
            'price'            => 75000,
            'delivery_method'  => DeliveryMethodEnum::BUNDLE,
            'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
            'status'           => PublicationStatusEnum::PUBLISHED,
            'details_json'     => [],
        ]);
        $parentOption->bundleComponents()->attach($component->id, ['allocation' => 75000]);

        $this->getJson(route('api.v1.shop.bundles.index'))->assertOk()->assertJsonPath('data.total', 0);

        // No Event::fake here: the real listener must dispatch the real job so the
        // snapshot is actually written. After-commit callbacks fire immediately
        // because the suite runs inside a transaction that never commits.
        $transactionManager = fakeAfterCommitEventsImmediately(null);

        $bundle->update(['status' => PublicationStatusEnum::PUBLISHED]);

        restoreAfterCommitEventManager($transactionManager);

        $product->refresh();

        expect($product->productable_status)->toBe(PublicationStatusEnum::PUBLISHED->value)
            ->and($product->has_published_delivery_option)->toBeTrue();

        $this->getJson(route('api.v1.shop.bundles.index'))->assertOk()->assertJsonPath('data.total', 1);
    });
});
