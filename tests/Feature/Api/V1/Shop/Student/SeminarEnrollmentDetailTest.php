<?php

declare(strict_types=1);

use App\Actions\Shop\Student\GetEnrollmentDetailAction;
use App\Enums\Product\DeliveryMethodEnum;
use App\Models\Product;
use App\Models\ProductDeliveryOption;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(GetEnrollmentDetailAction::class);

beforeEach(function (): void {
    $this->customer();
});

it('show returns niliroom delivery_access for live_session_niliroom enrollment', function (array $details, bool $isReady): void {
    $product        = Product::factory()->withSeminar()->create();
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'delivery_method'  => DeliveryMethodEnum::LIVE_SESSION_NILIROOM,
        'fulfillment_type' => DeliveryMethodEnum::LIVE_SESSION_NILIROOM->getFulfillmentType(),
        'details_json'     => $details,
        'product_id'       => $product->id,
    ]);

    $enrollment = createEnrollment(
        $this->user,
        DeliveryMethodEnum::LIVE_SESSION_NILIROOM,
        deliveryOption: $deliveryOption,
    );

    $response = $this->getJson(route('api.v1.shop.student.seminars.show', ['enrollment' => $enrollment->uuid]));

    $response->assertOk()
        ->assertJsonStructure(['data' => ['delivery_access', 'files', 'quizzes']]);
    $access = $response->json('data.delivery_access');
    expect($access['type'])->toBe(DeliveryMethodEnum::LIVE_SESSION_NILIROOM->value)
        ->and($access['is_ready'])->toBe($isReady)
        ->and($access['join_url_path'])->toBe(route('api.v1.shop.student.seminars.join', ['enrollment' => $enrollment->uuid], absolute: false));
})->with([
    'room configured' => [['nili_room_id' => 'NILI-ROOM-1'], true],
    'room missing'    => [[], false],
]);
