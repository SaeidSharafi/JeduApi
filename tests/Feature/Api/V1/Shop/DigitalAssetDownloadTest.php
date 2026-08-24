<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatusEnum;
use App\Enums\MediaTagEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Models\DigitalAsset;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\Storage;

uses(Tests\Support\Traits\AuthTestTrait::class);
uses(Tests\Support\Traits\FakeMediaTrait::class);

beforeEach(function (): void {
    $this->customer();
});

it('returns 403 when enrollment belongs to another user', function (): void {
    $other        = App\Models\User::factory()->create();
    $enrollment   = createEnrollment($other, DeliveryMethodEnum::DIRECT_DOWNLOAD);
    $digitalAsset = DigitalAsset::factory()->create();

    $this->getJson(route('api.v1.shop.student.digital-assets.download', [
        'enrollment'   => $enrollment->uuid,
        'digitalAsset' => $digitalAsset->uuid,
    ]))->assertForbidden();
});

it('returns 403 when enrollment is not active', function (): void {
    $digitalAsset                  = DigitalAsset::factory()->create();
    $enrollment                    = createDirectDownloadEnrollmentForAsset($this->user, $digitalAsset);
    $enrollment->enrollment_status = EnrollmentStatusEnum::AWAITING_PAYMENT;
    $enrollment->save();

    $this->getJson(route('api.v1.shop.student.digital-assets.download', [
        'enrollment'   => $enrollment->uuid,
        'digitalAsset' => $digitalAsset->uuid,
    ]))->assertForbidden();
});

it('returns 404 when digital asset does not belong to enrollment productable', function (): void {
    $digitalAsset   = DigitalAsset::factory()->create();
    $unrelatedAsset = DigitalAsset::factory()->create();
    $enrollment     = createDirectDownloadEnrollmentForAsset($this->user, $digitalAsset);
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);

    $this->getJson(route('api.v1.shop.student.digital-assets.download', [
        'enrollment'   => $enrollment->uuid,
        'digitalAsset' => $unrelatedAsset->uuid,
    ]))->assertNotFound();
});

it('streams file for owner with active enrollment and attached main media', function (): void {
    $this->fakeMedia();
    $digitalAsset = DigitalAsset::factory()->withFile()->create();
    $enrollment   = createDirectDownloadEnrollmentForAsset($this->user, $digitalAsset);
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);

    $this->getJson(route('api.v1.shop.student.digital-assets.download', [
        'enrollment'   => $enrollment->uuid,
        'digitalAsset' => $digitalAsset->uuid,
    ]))->assertOk();
});

it('returns 404 when no downloadable media file exists for digital asset', function (): void {
    $digitalAsset = DigitalAsset::factory()->create(); // no media attached
    $enrollment   = createDirectDownloadEnrollmentForAsset($this->user, $digitalAsset);
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);

    $this->getJson(route('api.v1.shop.student.digital-assets.download', [
        'enrollment'   => $enrollment->uuid,
        'digitalAsset' => $digitalAsset->uuid,
    ]))->assertNotFound();
});

it('returns 404 when productable is Course but digital asset not attached', function (): void {
    $course       = App\Models\Course::factory()->create();
    $digitalAsset = DigitalAsset::factory()->create(); // not attached to course
    $product      = Product::factory()->create([
        'productable_type' => $course::class,
        'productable_id'   => $course->id,
    ]);
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
        'fulfillment_type' => DeliveryMethodEnum::DIRECT_DOWNLOAD->getFulfillmentType(),
        'product_id'       => $product->id,
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, deliveryOption: $deliveryOption);
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);

    $this->getJson(route('api.v1.shop.student.digital-assets.download', [
        'enrollment'   => $enrollment->uuid,
        'digitalAsset' => $digitalAsset->uuid,
    ]))->assertNotFound();
});

it('streams file when productable is Course with attached digital asset', function (): void {
    $this->fakeMedia();
    $course       = App\Models\Course::factory()->create();
    $digitalAsset = DigitalAsset::factory()->withFile()->create();
    // Attach digital asset to course
    $course->digitalAssets()->attach($digitalAsset->id);

    $product = Product::factory()->create([
        'productable_type' => $course::class,
        'productable_id'   => $course->id,
    ]);
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
        'fulfillment_type' => DeliveryMethodEnum::DIRECT_DOWNLOAD->getFulfillmentType(),
        'product_id'       => $product->id,
    ]);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, deliveryOption: $deliveryOption);
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);

    $this->getJson(route('api.v1.shop.student.digital-assets.download', [
        'enrollment'   => $enrollment->uuid,
        'digitalAsset' => $digitalAsset->uuid,
    ]))->assertOk();
});

it('returns 404 when media record exists but file is missing from storage', function (): void {
    $this->fakeMedia();
    $digitalAsset = DigitalAsset::factory()->withFile()->create();
    $enrollment   = createDirectDownloadEnrollmentForAsset($this->user, $digitalAsset);
    $enrollment->update(['enrollment_status' => EnrollmentStatusEnum::ACTIVE]);

    // Delete the actual file from storage to simulate missing file
    $media = $digitalAsset->getMedia(MediaTagEnum::MAIN->value)->first();
    Storage::disk($media->disk)->delete($media->getDiskPath());

    $this->getJson(route('api.v1.shop.student.digital-assets.download', [
        'enrollment'   => $enrollment->uuid,
        'digitalAsset' => $digitalAsset->uuid,
    ]))->assertNotFound();
});

// ─── Helper ───────────────────────────────────────────────────────────────────

function createDirectDownloadEnrollmentForAsset(
    App\Models\User $customer,
    DigitalAsset $digitalAsset,
): Enrollment {
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
