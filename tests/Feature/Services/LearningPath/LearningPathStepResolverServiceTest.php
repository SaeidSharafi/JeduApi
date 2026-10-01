<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\LearningPathStepActionStateEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathStep;
use App\Models\Product;
use App\Models\Seminar;
use App\Services\LearningPath\LearningPathStepResolverService;
use App\Services\LearningPath\ResolvedLearningPathStep;
use App\Services\ProductPriceService;

covers(LearningPathStepResolverService::class, ResolvedLearningPathStep::class);

function indexLearningPathProductPrice(int $productId): void
{
    $product = Product::query()->findOrFail($productId);
    app(ProductPriceService::class)->updatePriceIndex($product->fresh());
}

it('returns no resolved steps for a path without steps', function (): void {
    $learningPath = LearningPath::factory()->create();

    $resolved = app(LearningPathStepResolverService::class)->resolve($learningPath);

    expect($resolved)->toBeEmpty();
});

it('resolves available, unavailable, and coming-soon learning path steps', function (): void {
    $availableCourse = Course::factory()->create([
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);
    $unavailableCourse = Course::factory()->create([
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);
    $comingSoonSeminar = Seminar::factory()->create([
        'status' => PublicationStatusEnum::PUBLISHED,
    ]);

    $availableProduct = Product::factory()
        ->withCourse($availableCourse)
        ->withDeliveryOptions(realData: [[
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
            'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
            'price'            => 1_200_000,
        ]])
        ->create([
            'status'     => PublicationStatusEnum::PUBLISHED,
            'is_visible' => true,
        ]);
    $unavailableProduct = Product::factory()
        ->withCourse($unavailableCourse)
        ->withDeliveryOptions(realData: [[
            'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
            'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
            'price'            => 900_000,
            'available_from'   => now()->addDay(),
        ]])
        ->create([
            'status'     => PublicationStatusEnum::PUBLISHED,
            'is_visible' => true,
        ]);
    indexLearningPathProductPrice((int) $availableProduct->getKey());
    indexLearningPathProductPrice((int) $unavailableProduct->getKey());

    $learningPath = LearningPath::factory()->create();
    LearningPathStep::factory()->create([
        'learning_path_id' => $learningPath->id,
        'position'         => 3,
        'productable_type' => 'seminar',
        'productable_id'   => $comingSoonSeminar->id,
    ]);
    LearningPathStep::factory()->create([
        'learning_path_id' => $learningPath->id,
        'position'         => 2,
        'productable_type' => 'course',
        'productable_id'   => $unavailableCourse->id,
    ]);
    LearningPathStep::factory()->create([
        'learning_path_id' => $learningPath->id,
        'position'         => 1,
        'productable_type' => 'course',
        'productable_id'   => $availableCourse->id,
    ]);

    $resolved = app(LearningPathStepResolverService::class)->resolve(
        $learningPath->load('steps.productable'),
    );
    $resolvedSteps = $resolved->all();

    expect($resolvedSteps)->toHaveCount(3)
        ->and($resolvedSteps[0])->toBeInstanceOf(ResolvedLearningPathStep::class)
        ->and($resolvedSteps[0]->product?->getKey())->toBe($availableProduct->getKey())
        ->and($resolvedSteps[0]->priceData?->min_price)->toBe(1_200_000)
        ->and($resolvedSteps[0]->state())->toBe(LearningPathStepActionStateEnum::AVAILABLE)
        ->and($resolvedSteps[1]->product?->getKey())->toBe($unavailableProduct->getKey())
        ->and($resolvedSteps[1]->priceData?->min_price)->toBe(900_000)
        ->and($resolvedSteps[1]->state())->toBe(LearningPathStepActionStateEnum::UNAVAILABLE)
        ->and($resolvedSteps[2]->product)->toBeNull()
        ->and($resolvedSteps[2]->priceData)->toBeNull()
        ->and($resolvedSteps[2]->state())->toBe(LearningPathStepActionStateEnum::COMING_SOON);
});
