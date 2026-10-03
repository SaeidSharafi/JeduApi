<?php

declare(strict_types=1);

use App\Contracts\Integrations\MoodleClientContract;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\ProvisioningReadinessEnum;
use App\Enums\ProvisioningStatusEnum;
use App\Models\Enrollment;
use App\Models\ProductDeliveryOption;
use App\Services\Enrollment\ProvisioningPlanResolver;

covers(ProvisioningPlanResolver::class);

function moodleDeliveryOption(): ProductDeliveryOption
{
    return ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
        'details_json'     => ['moodle_course_id' => 123],
    ]);
}

it('resolves a direct download as having no external providers', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD,
        'fulfillment_type' => FulfillmentTypeEnum::DIGITAL,
        'details_json'     => [],
    ]);

    $plan = app(ProvisioningPlanResolver::class)->resolve($deliveryOption);

    expect($plan['version'])->toBe(1)
        ->and($plan['providers'])->toBe([])
        ->and($plan['status'])->toBe(ProvisioningStatusEnum::HEALTHY->value)
        ->and($plan['resolved_at'])->not->toBeEmpty();
});

it('plans no provider for a live_session_niliroom seminar', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LIVE_SESSION_NILIROOM,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
        'details_json'     => ['nili_room_id' => 'NILI-ROOM-1'],
    ]);

    $plan = app(ProvisioningPlanResolver::class)->resolve($deliveryOption);

    expect($plan['providers'])->toBe([])
        ->and($plan['status'])->toBe(ProvisioningStatusEnum::HEALTHY->value);
});

it('resolves each live delivery method to its own provider and readiness reason', function (
    DeliveryMethodEnum $deliveryMethod,
    array $details,
    ProvisioningProviderEnum $expectedProvider,
): void {
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => $deliveryMethod,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
        'details_json'     => $details,
    ]);

    $plan = app(ProvisioningPlanResolver::class)->resolve($deliveryOption);

    expect($plan['providers'])->toBe([[
        'provider'            => $expectedProvider->value,
        'applicable'          => true,
        'readiness'           => ProvisioningReadinessEnum::DISABLED->value,
        'configuration_issue' => 'provider_disabled',
    ]])->and($plan['status'])->toBe(ProvisioningStatusEnum::MANUAL_ACTION_REQUIRED->value);
})->with([
    'skyroom'    => [DeliveryMethodEnum::LIVE_SESSION_SKYROOM, ['room_id' => 10], ProvisioningProviderEnum::SKYROOM],
    'spotplayer' => [DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER, ['spot_id' => 'SPOT-1'], ProvisioningProviderEnum::SPOTPLAYER],
    'moodle'     => [DeliveryMethodEnum::LMS_MOODLE, ['moodle_course_id' => 123], ProvisioningProviderEnum::MOODLE],
]);

it('plans the separate quiz provider for a quiz course on a live-session option', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LIVE_SESSION_SKYROOM,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
        'details_json'     => ['room_id' => 10, 'moodle_quiz_course_id' => 99],
    ]);

    $plan = app(ProvisioningPlanResolver::class)->resolve($deliveryOption);

    expect($plan['providers'])->toHaveCount(2)
        ->and($plan['providers'][0]['provider'])->toBe(ProvisioningProviderEnum::SKYROOM->value)
        ->and($plan['providers'][1]['provider'])->toBe(ProvisioningProviderEnum::MOODLE_QUIZ->value);
});

it('ignores an empty ims_course_code', function (): void {
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::DIRECT_DOWNLOAD,
        'fulfillment_type' => FulfillmentTypeEnum::DIGITAL,
        'details_json'     => ['ims_course_code' => ''],
    ]);

    expect(app(ProvisioningPlanResolver::class)->resolve($deliveryOption)['providers'])->toBe([]);
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

it('reports a ready integration as ready and issue-free', function (): void {
    $this->mock(MoodleClientContract::class, function ($mock): void {
        $mock->shouldReceive('isEnabled')->andReturnTrue();
        $mock->shouldReceive('isReady')->andReturnTrue();
    });

    $plan = app(ProvisioningPlanResolver::class)->resolve(moodleDeliveryOption());

    expect($plan['providers'][0]['readiness'])->toBe(ProvisioningReadinessEnum::READY->value)
        ->and($plan['providers'][0]['configuration_issue'])->toBeNull()
        ->and($plan['status'])->toBe(ProvisioningStatusEnum::READY->value);
});

it('reports an enabled but unready integration as invalid', function (): void {
    $this->mock(MoodleClientContract::class, function ($mock): void {
        $mock->shouldReceive('isEnabled')->andReturnTrue();
        $mock->shouldReceive('isReady')->andReturnFalse();
    });

    $plan = app(ProvisioningPlanResolver::class)->resolve(moodleDeliveryOption());

    expect($plan['providers'][0]['readiness'])->toBe(ProvisioningReadinessEnum::INVALID->value)
        ->and($plan['providers'][0]['configuration_issue'])->toBe('provider_invalid')
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
