<?php

declare(strict_types=1);

use App\Data\Shop\Product\Course\ProductFilterData;
use App\Data\Shop\Product\Course\ProductListRequestData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\CourseDifficultyLevelEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\TermStatusEnum;
use App\Events\ProductSearchIndexInvalidated;
use App\Jobs\SynchronizeProductSearchIndexJob;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductPrice;
use App\Models\Term;
use App\Services\ProductSearch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;

final class RecordingProductScoutEngine extends NullEngine
{
    public ?ScoutBuilder $lastBuilder = null;

    /** @var int[] */
    public array $updatedProductIds = [];

    /** @var int[] */
    public array $deletedProductIds = [];

    public function update($models): void
    {
        $this->updatedProductIds = $models->pluck('id')->all();
    }

    public function delete($models): void
    {
        $this->deletedProductIds = $models->pluck('id')->all();
    }

    public function paginate($builder, $perPage, $page): array
    {
        $this->lastBuilder = $builder;

        return [];
    }
}

it('queues Product search synchronization after an invalidation event', function (): void {
    Queue::fake();
    $transactionManager = fakeAfterCommitEventsImmediately(null);

    ProductSearchIndexInvalidated::dispatch([123]);
    restoreAfterCommitEventManager($transactionManager);

    Queue::assertPushed(SynchronizeProductSearchIndexJob::class, fn (SynchronizeProductSearchIndexJob $job): bool => $job->productIds === [123]);
});

it('builds the Product Typesense payload from normalized scalar values', function (): void {
    $course = Course::factory()->create([
        'status'           => PublicationStatusEnum::PUBLISHED,
        'difficulty_level' => CourseDifficultyLevelEnum::BEGINNER,
    ]);
    $term = Term::factory()->create(['status' => TermStatusEnum::ACTIVE]);

    $product = Product::withoutSyncingToSearch(fn (): Product => Product::factory()
        ->withCourse($course)
        ->create([
            'term_id'                       => $term->id,
            'productable_status'            => PublicationStatusEnum::DRAFT->value,
            'has_published_delivery_option' => false,
            'is_term_active'                => false,
            'earliest_registration_start'   => '2026-08-02',
            'latest_registration_end'       => '2026-08-20',
            'earliest_availability_start'   => '2026-08-03',
            'latest_availability_end'       => '2026-08-30',
            'near_capacity'                 => true,
            'max_capacity_utilization'      => 0.88,
            'event_start_at'                => '2026-09-10 09:30:00',
            'event_ended_at'                => '2026-09-11 09:30:00',
        ]));

    $category = Category::factory()->create();
    $product->categories()->attach($category);
    ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
    ]);
    ProductPrice::query()->create([
        'product_id'              => $product->id,
        'min_price'               => 120_000,
        'max_price'               => 120_000,
        'min_original_price'      => 150_000,
        'max_original_price'      => 150_000,
        'has_discount'            => true,
        'has_featured_price'      => false,
        'has_prepayment'          => false,
        'discount_percentage'     => 20,
        'highest_discount_amount' => 30_000,
    ]);

    $indexedProduct = $product->makeSearchableUsing(new Collection([$product]))->first();
    $payload        = $indexedProduct->toSearchableArray();

    expect($payload)
        ->not->toHaveKey('is_available_now')
        ->and($payload['id'])->toBe((string) $product->id)
        ->and($payload['price'])->toBe(120_000)
        ->and($payload['has_discount'])->toBeTrue()
        ->and($payload['productable_status'])->toBe(PublicationStatusEnum::DRAFT->value)
        ->and($payload['has_published_delivery_option'])->toBeFalse()
        ->and($payload['is_term_active'])->toBeFalse()
        ->and($payload['near_capacity'])->toBeTrue()
        ->and($payload['max_capacity_utilization'])->toBeFloat()->toBe(0.88)
        ->and($payload['category_ids'])->toBe([$category->id])
        ->and($payload['fulfillment_types'])->toBe([FulfillmentTypeEnum::ONLINE_SERVICE->value])
        ->and($payload['earliest_registration_start_ts'])->toBe($product->earliest_registration_start->startOfDay()->timestamp)
        ->and($payload['latest_registration_end_ts'])->toBe($product->latest_registration_end->endOfDay()->timestamp)
        ->and($payload['latest_availability_end_ts'])->toBe($product->latest_availability_end->endOfDay()->timestamp)
        ->and($payload['latest_event_ended_ts'])->toBe($product->event_ended_at->endOfDay()->timestamp);
});

it('uses Typesense-safe sentinels for unbounded Product dates', function (): void {
    $product = Product::withoutSyncingToSearch(fn (): Product => Product::factory()->create([
        'earliest_registration_start' => null,
        'latest_registration_end'     => null,
        'earliest_availability_start' => null,
        'latest_availability_end'     => null,
        'event_start_at'              => null,
        'event_ended_at'              => null,
    ]));

    $indexedProduct = $product->makeSearchableUsing(new Collection([$product]))->first();
    $payload        = $indexedProduct->toSearchableArray();

    expect($payload['earliest_registration_start_ts'])->toBe(0)
        ->and($payload['earliest_availability_start_ts'])->toBe(0)
        ->and($payload['earliest_event_start_ts'])->toBe(0)
        ->and($payload['latest_registration_end_ts'])->toBe(4102444800)
        ->and($payload['latest_availability_end_ts'])->toBe(4102444800)
        ->and($payload['latest_event_ended_ts'])->toBe(4102444800);
});

it('keeps the Product Typesense schema aligned with its emitted fields', function (): void {
    $product = Product::withoutSyncingToSearch(fn (): Product => Product::factory()->create());
    $payload = $product->makeSearchableUsing(new Collection([$product]))->first()->toSearchableArray();
    $fields  = collect(config('scout.typesense.model-settings.'.Product::class.'.collection-schema.fields'));

    $schemaFieldNames  = $fields->pluck('name')->reject(fn (string $name): bool => $name === 'embedding')->sort()->values();
    $payloadFieldNames = collect(array_keys($payload))->reject(fn (string $name): bool => $name === 'id')->sort()->values();
    $capacityField     = $fields->firstWhere('name', 'max_capacity_utilization');

    expect($schemaFieldNames->all())->toBe($payloadFieldNames->all())
        ->and($fields->pluck('name'))->not->toContain('is_available_now')
        ->and($fields->firstWhere('name', 'near_capacity'))->toMatchArray([
            'type'  => 'bool',
            'facet' => true,
        ])
        ->and($capacityField)->toMatchArray([
            'type'  => 'float',
            'facet' => true,
            'sort'  => true,
        ]);
});

it('composes Typesense capacity and price constraints and sorts utilization descending', function (): void {
    $engine  = new RecordingProductScoutEngine();
    $manager = app(EngineManager::class);
    $manager->forgetDrivers();
    $manager->extend('typesense', fn (): RecordingProductScoutEngine => $engine);
    config()->set('scout.driver', 'typesense');

    $request = new ProductListRequestData(
        filter: new ProductFilterData(
            min_price: 100_000,
            max_price: 500_000,
            near_capacity_only: true,
            capacity_threshold: 0.9,
        ),
        sortBy: 'capacity_utilization',
        sortOrder: 'asc',
    );

    (new ProductSearch(typesenseAvailability: fn (): bool => true))->searchScout($request);

    expect($engine->lastBuilder)->not->toBeNull()
        ->and($engine->lastBuilder->options)->not->toHaveKey('filter_by')
        ->and($engine->lastBuilder->wheres)->toMatchArray([
            'status'                        => PublicationStatusEnum::PUBLISHED->value,
            'is_visible'                    => true,
            'productable_status'            => PublicationStatusEnum::PUBLISHED->value,
            'has_published_delivery_option' => true,
            'is_term_active'                => true,
            'price'                         => ['[', 100_000, '..', 500_000, ']'],
            'max_capacity_utilization'      => ['>=', 0.9],
        ])
        ->and($engine->lastBuilder->wheres)->toHaveKeys([
            'earliest_availability_start_ts',
            'latest_availability_end_ts',
            'latest_event_ended_ts',
        ])
        ->and($engine->lastBuilder->orders)->toBe([
            ['column' => 'max_capacity_utilization', 'direction' => 'desc'],
        ]);
});

it('batch-upserts eligible Products and removes ineligible Products', function (): void {
    config()->set('products.availability.use_denormalized', true);
    config()->set('scout.driver', 'null');

    [$eligible, $ineligible] = Product::withoutSyncingToSearch(function (): array {
        $eligible = Product::factory()->create([
            'productable_status'            => PublicationStatusEnum::PUBLISHED->value,
            'has_published_delivery_option' => true,
            'is_term_active'                => true,
        ]);
        $ineligible = Product::factory()->create([
            'productable_status'            => PublicationStatusEnum::DRAFT->value,
            'has_published_delivery_option' => true,
            'is_term_active'                => true,
        ]);

        return [$eligible, $ineligible];
    });

    $engine  = new RecordingProductScoutEngine();
    $manager = app(EngineManager::class);
    $manager->forgetDrivers();
    $manager->extend('typesense', fn (): RecordingProductScoutEngine => $engine);
    config()->set('scout.driver', 'typesense');

    (new SynchronizeProductSearchIndexJob([$eligible->id, $ineligible->id]))->handle();

    expect($engine->updatedProductIds)->toBe([$eligible->id])
        ->and($engine->deletedProductIds)->toBe([$ineligible->id]);
});
