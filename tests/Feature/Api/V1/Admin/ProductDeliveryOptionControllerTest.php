<?php

use App\Models\ProductDeliveryOption;

uses(\Tests\AuthTestTrait::class);
describe('User with permissions', function () {
    beforeEach(function () {
        $this->product = App\Models\Product::factory()->create();
        $this->simpleData = ProductDeliveryOption::factory()
            ->make(
                [
                    'product_id' =>  $this->product->id,
                    'fulfillment_type' => \App\Enums\FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method' => \App\Enums\DeliveryMethodEnum::LMS_MOODLE,
                ]
            )->toArray();
        $this->simpleData['details'] = [
            'course_idnumber' => 'course-id-123',
            'activity_id' => null,
        ];
    });
    it('should return a list of delivery options for a product', function () {
        $this->authorized_user([
            \App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_VIEW_ANY
        ]);
        $product = App\Models\Product::factory()->create();
        $deliveryOptions = App\Models\ProductDeliveryOption::factory()->count(3)
            ->create(['product_id' => $product->id]);

        $response = $this->getJson(route('api.v1.admin.delivery-option.index', ['product' => $product->id]));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['name' => $deliveryOptions->first()->name])
        ;
    });

    it('should create a new delivery option for a product', function () {
        $this->authorized_user([
            \App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE
        ]);

        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $this->product->id]), $this->simpleData);

        $response->assertCreated()
            ->assertJsonFragment(['name' => $this->simpleData['name']]);
    });
    it('should return the specified delivery option details', function () {
        $this->authorized_user([
            \App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_VIEW
        ]);
        $deliveryOption = ProductDeliveryOption::factory()->create();

        $response = $this->getJson(route('api.v1.admin.delivery-option.show',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]));

        $response->assertOk()
            ->assertJsonFragment(['id' => $deliveryOption->id, 'name' => $deliveryOption->name]);
    });
    it('should update the specified delivery option', function () {
        $this->authorized_user([
            \App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE
        ]);
        $deliveryOption = ProductDeliveryOption::factory()->create(
            [
                'product_id' => $this->product->id,
                'fulfillment_type' => \App\Enums\FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method' => \App\Enums\DeliveryMethodEnum::LMS_MOODLE,
            ]
        )->fresh();
        $data = $deliveryOption->toArray();
        $data['name'] = $this->simpleData['name'];
        $data['details'] = [
            'course_idnumber' => 'course-id-123',
            'activity_id' => null,
        ];

        $response = $this->putJson(route('api.v1.admin.delivery-option.update',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]), $data);

        $response->assertOk()
            ->assertJsonFragment(['id' => $deliveryOption->id, 'name' => $data['name']]);
    });
    it('should delete the specified delivery option', function () {
        $this->authorized_user([
            \App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_DELETE
        ]);
        $deliveryOption = ProductDeliveryOption::factory()->create();

        $response = $this->deleteJson(route('api.v1.admin.delivery-option.destroy',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]));

        $response->assertNoContent();
    });
});
describe('User without permissions', function () {
    beforeEach(function () {
        $this->product = App\Models\Product::factory()->create();
        $this->simpleData = ProductDeliveryOption::factory()
            ->make(
                [
                    'product_id' =>  $this->product->id,
                    'fulfillment_type' => \App\Enums\FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method' => \App\Enums\DeliveryMethodEnum::LMS_MOODLE,
                ]
            )->toArray();
        $this->simpleData['details'] = [
            'course_idnumber' => 'course-id-123',
            'activity_id' => null,
        ];
        $this->unauthorized_user();
    });
    it('should return 403 if user does not have permission to view delivery options', function () {

        $product = App\Models\Product::factory()->create();

        $response = $this->getJson(route('api.v1.admin.delivery-option.index', ['product' => $product->id]));

        $response->assertForbidden();
    });
    it('should return 403 if user does not have permission to create delivery options', function () {

        $product = App\Models\Product::factory()->create();
        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $this->simpleData);

        $response->assertForbidden();
    });
    it('should return 403 if user does not have permission to update delivery options', function () {

        $deliveryOption = App\Models\ProductDeliveryOption::factory()->create(
            [
                'product_id' => $this->product->id,
                'fulfillment_type' => \App\Enums\FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method' => \App\Enums\DeliveryMethodEnum::LMS_MOODLE,
            ]
        );
        $response = $this->putJson(route('api.v1.admin.delivery-option.update',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]), $this->simpleData);

        $response->assertForbidden();
    });
    it('should return 403 if user does not have permission to delete delivery options', function () {

        $deliveryOption = ProductDeliveryOption::factory()->create();

        $response = $this->deleteJson(route('api.v1.admin.delivery-option.destroy',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]));

        $response->assertForbidden();
    });

});

describe('validation', function () {
    it('should return validation error for required fields', function () {
        $this->authorized_user([
            \App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE
        ]);
        $product = App\Models\Product::factory()->create();
        $data = ProductDeliveryOption::factory()
            ->make(['product_id' => $product->id, 'name' => null])->toArray();

        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
    it('should return error if delivery option doesn\'t belong to fulfillment type', function () {
        $this->authorized_user([
            \App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE
        ]);
        $product = App\Models\Product::factory()->create();
        $data = ProductDeliveryOption::factory()
            ->make([
                'product_id' => $product->id,
                'fulfillment_type' => \App\Enums\FulfillmentTypeEnum::OFFILNE_SERVICE->value,
                'delivery_method' => \App\Enums\DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
            ])->toArray();
        $data['details'] = [
            'file_id' => 'file-id-123',
        ];
        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_method']);
    });

    it('should return correct validation errors for each delivery option detials', function () {
        $this->authorized_user([
            \App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE
        ]);
        $product = App\Models\Product::factory()->create();
        $data = ProductDeliveryOption::factory()
            ->make([
                'product_id' => $product->id,
                'fulfillment_type' => \App\Enums\FulfillmentTypeEnum::DIGITAL->value,
                'delivery_method' => \App\Enums\DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
            ])->toArray();
        $data['details'] = [];

        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.max_downloads']);

        $data['fulfillment_type'] = \App\Enums\FulfillmentTypeEnum::ONLINE_SERVICE->value;
        $data['delivery_method'] = \App\Enums\DeliveryMethodEnum::LMS_MOODLE->value;

        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.course_idnumber']);

        $data['fulfillment_type'] = \App\Enums\FulfillmentTypeEnum::ONLINE_SERVICE->value;
        $data['delivery_method'] = \App\Enums\DeliveryMethodEnum::LIVE_SESSION_BBB->value;


        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details']);

        $data['delivery_method'] = \App\Enums\DeliveryMethodEnum::LIVE_SESSION_SKYROOM->value;
        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.meeting_name_identifier']);

        $data['fulfillment_type'] = \App\Enums\FulfillmentTypeEnum::OFFILNE_SERVICE->value;
        $data['delivery_method'] = \App\Enums\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->value;
        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.course_id']);

        $data['fulfillment_type'] = \App\Enums\FulfillmentTypeEnum::IN_PERSON_SERVICE->value;
        $data['delivery_method'] = \App\Enums\DeliveryMethodEnum::IN_PERSON->value;
        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(
                [
                'details.location',
                'details.duration',
                'details.schedule',
            ]);
    });
});
