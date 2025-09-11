<?php

declare(strict_types=1);

use App\Enums\DeliveryMethodEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductDeliveryOption;

uses(Tests\AuthTestTrait::class);
beforeEach(function () {
    $this->customer();
});
it('should filter by fulfillment type', function () {
    createEnrolment($this->user, DeliveryMethodEnum::IN_PERSON, 2);
    createEnrolment($this->user, DeliveryMethodEnum::LMS_MOODLE);
    $this->getJson(route('api.v1.shop.my-courses.index', [
        'filter' => ['fulfillment_type' => App\Enums\FulfillmentTypeEnum::ONLINE_SERVICE->value],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.product.fullfilment_type.value',
            App\Enums\FulfillmentTypeEnum::ONLINE_SERVICE->value);
});
it('should filter by product name', function () {
    $product = App\Models\Product::factory()->create([
        'name' => 'Test Product',
    ]);
    $deliveryOption = ProductDeliveryOption::factory()
        ->create([
            'name'            => 'Test Product',
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE->value,
            'product_id'      => $product->id,
        ]);
    createEnrolment($this->user, DeliveryMethodEnum::LMS_MOODLE, 5);
    createEnrolment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $this->getJson(route('api.v1.shop.my-courses.index', [
        'filter' => ['name' => 'Test Product'],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.product.name', 'Test Product');
});
it('should paginate results', function () {
    createEnrolment($this->user, DeliveryMethodEnum::LMS_MOODLE, count: 5);
    $this->getJson(route('api.v1.shop.my-courses.index', [
        'per_page' => 1,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.total', 5); // Assuming there are 3 enrolments in total
});
it('shows current user specific enrolment details', function () {
    $product = App\Models\Product::factory()->create([
        'name' => 'Test Product',
    ]);
    $deliveryOption = ProductDeliveryOption::factory()
        ->create([
            'name'            => 'Test Product',
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE->value,
            'product_id'      => $product->id,
        ]);
    createEnrolment($this->user, DeliveryMethodEnum::LMS_MOODLE, 5);
    $enrolment = createEnrolment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $response  = $this->getJson(route('api.v1.shop.my-courses.show', [
        'enrolment' => $enrolment->uuid,
        'per_page'  => 1,
    ]));

    $response->assertOk();
    $response->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($enrolment) {
        $enrolment->load('product');
        $json->where('data.uuid', $enrolment->uuid)
            ->where('data.enrollment_status', [
                'value' => $enrolment->enrollment_status->value,
                'label' => $enrolment->enrollment_status->translate(),
            ])
            ->where('data.product.name', $enrolment->product->name)
            ->etc();
    });

});
it('does not show other users enrolment details', function () {

    $user      = App\Models\User::factory()->create()->fresh();
    $enrolment = createEnrolment($user, DeliveryMethodEnum::LMS_MOODLE);
    $response  = $this->getJson(route('api.v1.shop.my-courses.show', [
        'enrolment' => $enrolment->uuid,
        'per_page'  => 1,
    ]));

    $response->assertNotFound();
    $response->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);

});
function createEnrolment(
    App\Models\User|Illuminate\Contracts\Auth\Authenticatable $customer,
    DeliveryMethodEnum $deliveryMethod,
    int $count = 1,
    ?ProductDeliveryOption $deliveryOption = null,
): App\Models\Enrolment {
    $order = Order::factory()->create(
        [
            'customer_id'            => $customer->id,
            'customer_email'         => $customer->email,
            'customer_phone'         => $customer->phone,
            'customer_first_name'    => $customer->first_name,
            'customer_last_name'     => $customer->last_name,
            'customer_snapshot_json' => $customer->toArray(),
        ]
    );

    $product = $deliveryOption
        ?: ProductDeliveryOption::factory()->create([
            'delivery_method'  => $deliveryMethod->value,
            'fulfillment_type' => $deliveryMethod->getFulfillmentType(),
        ]);

    $order_item = OrderItem::factory()
        ->withEnrolment()
        ->count($count)
        ->create([
            'order_id'                   => $order->id,
            'product_delivery_option_id' => $product->id,
            'name'                       => $product->name,
            'sku'                        => $product->sku,
            'product_data_snapshot_json' => $product->product->toArray(),
        ])->fresh();

    return $order_item->first()->enrolment;
}
