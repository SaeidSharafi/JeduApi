<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;

uses(Tests\Support\Traits\AuthTestTrait::class);
uses(Tests\Support\Traits\FakeMediaTrait::class);

beforeEach(function (): void {
    $this->customer();
});

it('returns only direct_download enrollments', function (): void {
    $asset = App\Models\DigitalAsset::factory()->create();
    createEnrollmentForAsset($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, $asset);
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);

    $response = $this->getJson(route('api.v1.shop.student.digital-assets.index'));

    $response->assertOk();
    $data = $response->json('data.data');
    expect($data)->toHaveCount(1)
        ->and($data[0])->toHaveKeys(['uuid', 'enrollment_uuid', 'name', 'thumbnail_url', 'file_type', 'file_type_label', 'size_bytes', 'size_label', 'download_url']);
});

it('does not return other users digital asset enrollments', function (): void {
    $other = App\Models\User::factory()->create();
    $asset = App\Models\DigitalAsset::factory()->create();
    createEnrollmentForAsset($other, DeliveryMethodEnum::DIRECT_DOWNLOAD, $asset);

    $response = $this->getJson(route('api.v1.shop.student.digital-assets.index'));

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(0);
});

it('returns empty list when user has no direct_download enrollments', function (): void {
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
    createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON);

    $response = $this->getJson(route('api.v1.shop.student.digital-assets.index'));

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(0);
});

it('paginates digital asset enrollments', function (): void {
    $asset1 = App\Models\DigitalAsset::factory()->create();
    $asset2 = App\Models\DigitalAsset::factory()->create();
    $asset3 = App\Models\DigitalAsset::factory()->create();
    createEnrollmentForAsset($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, $asset1);
    createEnrollmentForAsset($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, $asset2);
    createEnrollmentForAsset($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, $asset3);

    $response = $this->getJson(route('api.v1.shop.student.digital-assets.index', ['per_page' => 1]));

    $response->assertOk();
    expect($response->json('data.data'))->toHaveCount(1)
        ->and($response->json('data.total'))->toBe(3);
});

it('returns flat row with file metadata and human labels', function (): void {
    $this->fakeMedia();
    $asset      = App\Models\DigitalAsset::factory()->withFile()->create();
    $enrollment = createEnrollmentForAsset($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, $asset);

    $response = $this->getJson(route('api.v1.shop.student.digital-assets.index'));

    $response->assertOk();
    $row   = $response->json('data.data.0');
    $media = $asset->getMedia(App\Enums\MediaTagEnum::MAIN->value)->first();
    expect($row['uuid'])->toBe($asset->uuid)
        ->and($row['enrollment_uuid'])->toBe($enrollment->uuid)
        ->and($row['name'])->toBe($asset->full_name)
        ->and($row['thumbnail_url'])->toBe($asset->thumbnail_url)
        ->and($row['file_type'])->toBe($media?->mime_type)
        ->and($row['file_type_label'])->toBe($media?->extension !== null ? mb_strtoupper($media->extension) : null)
        ->and($row['size_bytes'])->toBe((int) $media?->size)
        ->and($row['size_label'])->toBe(formatFileSize((int) $media?->size))
        ->and($row['download_url'])->toContain($enrollment->uuid)
        ->and($row['download_url'])->toContain($asset->uuid);
});

it('requires authentication', function (): void {
    // Clear actingAs user set in beforeEach via reflection (RequestGuard::setUser requires Authenticatable)
    $guard = auth('user');
    $ref   = new ReflectionClass($guard);
    if ($ref->hasProperty('user')) {
        $prop = $ref->getProperty('user');
        $prop->setAccessible(true);
        $prop->setValue($guard, null);
    }

    $this->getJson(route('api.v1.shop.student.digital-assets.index'))->assertUnauthorized();
});

function createEnrollmentForAsset(
    App\Models\User $customer,
    DeliveryMethodEnum $deliveryMethod,
    App\Models\DigitalAsset $asset,
): App\Models\Enrollment {
    $product = App\Models\Product::factory()->create([
        'productable_type' => App\Enums\Product\ProductableEnum::DIGITAL_ASSET->value,
        'productable_id'   => $asset->id,
    ]);

    $option = App\Models\ProductDeliveryOption::factory()->create([
        'delivery_method'  => $deliveryMethod->value,
        'fulfillment_type' => $deliveryMethod->getFulfillmentType(),
        'product_id'       => $product->id,
    ]);

    return createEnrollment($customer, $deliveryMethod, deliveryOption: $option);
}
