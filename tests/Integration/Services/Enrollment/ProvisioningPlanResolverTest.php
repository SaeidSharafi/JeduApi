<?php

declare(strict_types=1);

use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Models\Enrollment;
use App\Models\ProductDeliveryOption;
use App\Services\Enrollment\ProvisioningPlanResolver;

it('resolves a direct download as having no external providers', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD,
        'fulfillment_type' => FulfillmentTypeEnum::DIGITAL,
        'details_json'     => [],
    ]);

    $plan = app(ProvisioningPlanResolver::class)->resolve($deliveryOption);

    expect($plan['version'])->toBe(1)
        ->and($plan['providers'])->toBe([])
        ->and($plan['status'])->toBe(ProvisioningStatusEnum::HEALTHY->value);
});

it('includes applicable providers even when their integration is disabled', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
        'details_json'     => [
            'moodle_course_id' => 123,
            'ims_course_code'  => 'IMS-123',
        ],
    ]);

    $plan = app(ProvisioningPlanResolver::class)->resolve($deliveryOption);

    expect($plan['providers'])->toHaveCount(2)
        ->and(collect($plan['providers'])->pluck('provider')->all())->toBe(['ims', 'moodle'])
        ->and(collect($plan['providers'])->pluck('readiness')->all())->toBe(['disabled', 'disabled'])
        ->and($plan['status'])->toBe(ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED->value);
});

it('keeps an enrollment healthy when the canonical plan has no providers', function (): void {
    $enrollment = Enrollment::factory()->create();
    $enrollment->update([
        'enrollment_status' => 'active',
        'provisioning_plan' => [
            'version'     => 1,
            'providers'   => [],
            'status'      => ProvisioningStatusEnum::HEALTHY->value,
            'resolved_at' => now()->toISOString(),
        ],
        'provisioning_status' => ProvisioningStatusEnum::READY,
    ]);

    $enrollment->activateIfNoProvisioningRequired();

    expect($enrollment->fresh()->enrollment_status->value)->toBe('active')
        ->and($enrollment->fresh()->provisioning_status)->toBe(ProvisioningStatusEnum::HEALTHY);
});
