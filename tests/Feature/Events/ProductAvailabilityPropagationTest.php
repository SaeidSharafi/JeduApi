<?php

declare(strict_types=1);

use App\Actions\Admin\Product\CreateProductAction;
use App\Actions\Admin\Product\UpdateProductAction;
use App\Actions\Admin\ProductDeliveryOption\UpdateProductDeliveryOptionAction;
use App\Data\Admin\Product\ProductCreateData;
use App\Data\Admin\Product\ProductUpdateData;
use App\Data\Admin\ProductDeliveryOption\ProductDeliveryOptionUpdateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\TermStatusEnum;
use App\Events\EnrollmentStatusChanged;
use App\Events\ProductAvailabilityCacheInvalidated;
use App\Events\ProductCacheInvalidated;
use App\Events\ProductSearchIndexInvalidated;
use App\Jobs\UpdateProductAvailabilityJob;
use App\Listeners\UpdateProductDeliveryOptionEnrolledCount;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('propagates productable status in both directions (leave AND enter published)', function (): void {
    Queue::fake();
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->withCourse($course)->create();

    $course->update(['status' => PublicationStatusEnum::DRAFT]);

    Queue::assertPushed(UpdateProductAvailabilityJob::class, fn (UpdateProductAvailabilityJob $job): bool => $job->productIds === [$product->id]);

    $course->update(['status' => PublicationStatusEnum::PUBLISHED]);

    // First publish flips availability too: a DRAFT productable is invisible,
    // publishing it makes every published shell purchasable.
    Queue::assertPushed(UpdateProductAvailabilityJob::class, fn (UpdateProductAvailabilityJob $job): bool => $job->productIds === [$product->id]);
});

it('propagates term status in both directions (leave AND enter active)', function (): void {
    Queue::fake();
    $term     = Term::factory()->create(['status' => TermStatusEnum::ACTIVE]);
    $products = Product::factory()->count(2)->create(['term_id' => $term->id]);

    $term->update(['status' => TermStatusEnum::INACTIVE]);

    Queue::assertPushed(UpdateProductAvailabilityJob::class, function (UpdateProductAvailabilityJob $job) use ($products): bool {
        return $job->productIds === $products->pluck('id')->all();
    });

    $term->update(['status' => TermStatusEnum::ACTIVE]);

    Queue::assertPushed(UpdateProductAvailabilityJob::class, function (UpdateProductAvailabilityJob $job) use ($products): bool {
        return $job->productIds === $products->pluck('id')->all();
    });
});

it('propagates a delivery option status in both directions (leave AND enter published)', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);
    Queue::fake();
    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);

    app(UpdateProductDeliveryOptionAction::class)->handle(
        productDeliveryOptionData($deliveryOption, PublicationStatusEnum::DRAFT),
        $deliveryOption,
    );
    restoreAfterCommitEventManager($transactionManager);

    Queue::assertPushed(UpdateProductAvailabilityJob::class, fn (UpdateProductAvailabilityJob $job): bool => $job->productIds === [$deliveryOption->product_id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$deliveryOption->product_id]);

    $deliveryOption->updateQuietly(['status' => PublicationStatusEnum::DRAFT]);
    Queue::fake();
    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);

    app(UpdateProductDeliveryOptionAction::class)->handle(
        productDeliveryOptionData($deliveryOption, PublicationStatusEnum::PUBLISHED),
        $deliveryOption,
    );
    restoreAfterCommitEventManager($transactionManager);

    // First publish (DRAFT -> PUBLISHED) must invalidate availability + search:
    // the option was not published before, so availability flips on publish.
    Queue::assertPushed(UpdateProductAvailabilityJob::class, fn (UpdateProductAvailabilityJob $job): bool => $job->productIds === [$deliveryOption->product_id]);
    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$deliveryOption->product_id]);
});

it('synchronizes search after product category mutations', function (): void {
    $transactionManager = fakeAfterCommitEventsImmediately([
        ProductAvailabilityCacheInvalidated::class,
        ProductCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    $course   = Course::factory()->create();
    $product  = Product::factory()->withCourse($course)->create();
    $category = Category::factory()->create();

    $created = app(CreateProductAction::class)->handle(new ProductCreateData(
        force_create: false,
        productable_type: $product->productable_type,
        productable_id: $course->id,
        vendor_id: $product->vendor_id,
        term_id: $product->term_id,
        status: PublicationStatusEnum::DRAFT->value,
        is_visible: false,
        short_description: 'Created product',
        short_name: 'Created',
        name: 'Created product',
        is_featured: false,
        categories: [$category->id],
        details_json: [],
    ));
    restoreAfterCommitEventManager($transactionManager);

    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$created->id]);
    expect($created->categories()->pluck('categories.id')->all())->toBe([$category->id]);

    $transactionManager = fakeAfterCommitEventsImmediately([
        ProductAvailabilityCacheInvalidated::class,
        ProductCacheInvalidated::class,
        ProductSearchIndexInvalidated::class,
    ]);

    app(UpdateProductAction::class)->handle(new ProductUpdateData(
        vendor_id: $product->vendor_id,
        term_id: $product->term_id,
        status: $product->status->value,
        is_visible: $product->is_visible,
        short_description: $product->short_description,
        short_name: $product->short_name,
        name: 'Updated product',
        is_featured: $product->is_featured,
        categories: [$category->id],
        details_json: $product->details_json,
    ), $product);
    restoreAfterCommitEventManager($transactionManager);

    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);
    expect($product->categories()->pluck('categories.id')->all())->toBe([$category->id]);
});

it('synchronizes search for productable text and category slug changes', function (): void {
    $course   = Course::factory()->create();
    $product  = Product::factory()->withCourse($course)->create();
    $category = Category::factory()->create();
    $product->categories()->attach($category);

    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);
    $course->update(['description' => 'Updated indexed description']);
    restoreAfterCommitEventManager($transactionManager);

    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);

    $transactionManager = fakeAfterCommitEventsImmediately(ProductSearchIndexInvalidated::class);
    $category->update(['slug' => 'updated-indexed-slug']);
    restoreAfterCommitEventManager($transactionManager);

    Event::assertDispatched(ProductSearchIndexInvalidated::class, fn (ProductSearchIndexInvalidated $event): bool => $event->productIds === [$product->id]);
});

it('always refreshes availability after enrolled count projection', function (): void {
    Queue::fake();
    $enrollment = Enrollment::factory()->create([
        'enrollment_status' => EnrollmentStatusEnum::ACTIVE,
    ]);

    (new UpdateProductDeliveryOptionEnrolledCount())->handle(new EnrollmentStatusChanged($enrollment));

    Queue::assertPushed(UpdateProductAvailabilityJob::class, fn (UpdateProductAvailabilityJob $job): bool => $job->productIds === [$enrollment->productDeliveryOption->product_id]);
});

function productDeliveryOptionData(
    ProductDeliveryOption $deliveryOption,
    PublicationStatusEnum $status,
): ProductDeliveryOptionUpdateData {
    return new ProductDeliveryOptionUpdateData(
        name: $deliveryOption->name,
        sku: $deliveryOption->sku,
        price: $deliveryOption->price,
        status: $status->value,
        details_json: $deliveryOption->details_json,
        teachers: [],
        capacity: $deliveryOption->capacity,
        is_prepayment_available: $deliveryOption->is_prepayment_available,
        prepayment_amount: $deliveryOption->prepayment_amount,
        is_featured: $deliveryOption->is_featured,
        featured_price: $deliveryOption->featured_price,
        featured_price_start_date: $deliveryOption->featured_price_start_date,
        featured_price_end_date: $deliveryOption->featured_price_end_date,
        registration_start_date: $deliveryOption->registration_start_date,
        registration_end_date: $deliveryOption->registration_end_date,
        available_from: $deliveryOption->available_from,
        available_to: $deliveryOption->available_to,
        access_days: $deliveryOption->access_days,
    );
}
