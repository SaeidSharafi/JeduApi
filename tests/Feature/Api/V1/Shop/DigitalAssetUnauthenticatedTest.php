<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;

use function Pest\Laravel\getJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

it('returns 401 for unauthenticated request to digital assets index', function (): void {
    getJson(route('api.v1.shop.student.digital-assets.index'))
        ->assertUnauthorized();
});

it('returns 401 for unauthenticated download request', function (): void {
    $user         = User::factory()->create();
    $digitalAsset = DigitalAsset::factory()->create();
    $enrollment   = createUnauthEnrollmentForAsset($user, $digitalAsset);

    getJson(route('api.v1.shop.student.digital-assets.download', [
        'enrollment'   => $enrollment->uuid,
        'digitalAsset' => $digitalAsset->id,
    ]))->assertUnauthorized();
});

// ─── Helper ───────────────────────────────────────────────────────────────────

function createUnauthEnrollmentForAsset(User $customer, DigitalAsset $digitalAsset): Enrollment
{
    $product = Product::factory()->create([
        'productable_type' => DigitalAsset::class,
        'productable_id'   => $digitalAsset->id,
    ]);

    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
        'fulfillment_type' => DeliveryMethodEnum::DIRECT_DOWNLOAD->getFulfillmentType(),
        'product_id'       => $product->id,
    ]);

    return createEnrollment($customer, DeliveryMethodEnum::DIRECT_DOWNLOAD, deliveryOption: $deliveryOption);
}
