<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\TermStatusEnum;
use App\Events\ProductSearchIndexInvalidated;
use App\Jobs\UpdateProductAvailabilityJob;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use Illuminate\Support\Facades\Event;

it('recomputes the complete product availability snapshot', function (): void {
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $term    = Term::factory()->create(['status' => TermStatusEnum::ACTIVE]);
    $product = Product::factory()->withCourse($course)->create([
        'term_id' => $term->id,
    ]);

    ProductDeliveryOption::factory()->create([
        'product_id'              => $product->id,
        'status'                  => PublicationStatusEnum::PUBLISHED,
        'registration_start_date' => '2026-08-01',
        'registration_end_date'   => '2026-08-20',
        'available_from'          => '2026-08-02',
        'available_to'            => '2026-08-30',
        'capacity'                => 10,
        'enrolled_count'          => 9,
    ]);

    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);
    (new UpdateProductAvailabilityJob([$product->id]))->handle();
    restoreAfterCommitEventManager($transactionManager);

    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);

    $product->refresh();

    expect($product->has_published_delivery_option)->toBeTrue()
        ->and($product->productable_status)->toBe(PublicationStatusEnum::PUBLISHED->value)
        ->and($product->is_term_active)->toBeTrue()
        ->and($product->earliest_registration_start?->toDateString())->toBe('2026-08-01')
        ->and($product->latest_registration_end?->toDateString())->toBe('2026-08-20')
        ->and($product->earliest_availability_start?->toDateString())->toBe('2026-08-02')
        ->and($product->latest_availability_end?->toDateString())->toBe('2026-08-30')
        ->and($product->near_capacity)->toBeTrue()
        ->and((float) $product->max_capacity_utilization)->toBe(0.9);

    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);
    (new UpdateProductAvailabilityJob([$product->id]))->handle();
    restoreAfterCommitEventManager($transactionManager);

    Event::assertNotDispatched(ProductSearchIndexInvalidated::class);
});

it('handles an empty product id list', function (): void {
    (new UpdateProductAvailabilityJob([]))->handle();

    expect(true)->toBeTrue();
});
