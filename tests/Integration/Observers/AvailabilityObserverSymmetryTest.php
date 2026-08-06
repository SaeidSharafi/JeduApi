<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Models\Course;
use App\Models\Product;
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
