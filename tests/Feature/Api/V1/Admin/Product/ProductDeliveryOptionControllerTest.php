<?php

declare(strict_types=1);

use App\Models\ProductDeliveryOption;
use Illuminate\Testing\Fluent\AssertableJson;

uses(Tests\AuthTestTrait::class);
describe('User with permissions', function (): void {
    beforeEach(function (): void {
        $this->product    = App\Models\Product::factory()->create();
        $this->simpleData = ProductDeliveryOption::factory()
            ->make(
                [
                    'product_id'       => $this->product->id,
                    'fulfillment_type' => \App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method'  => \App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
                ]
            )->toArray();
        $this->simpleData['details'] = [
            'course_idnumber' => 'course-id-123',
            'activity_id'     => null,
        ];
        $this->teachers               = App\Models\Teacher::factory()->count(3)->create();
        $this->simpleData['teachers'] = $this->teachers->pluck('id')->toArray();
    });
    it('should return a list of delivery options for a product', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_VIEW_ANY,
        ]);
        $product         = App\Models\Product::factory()->create();
        ProductDeliveryOption::factory()
            ->withTeachers(3, true)
            ->count(3)
            ->create(['product_id' => $product->id]);
        $deliveryOptions = ProductDeliveryOption::query()
            ->with('teachers', fn($q) => $q->orderBy('id'))
            ->get();
        $response = $this->getJson(route('api.v1.admin.delivery-option.index', ['product' => $product->id]));
        $response->assertOk()
            ->assertJsonCount(3, 'data');
        $actualDataItems = collect($response->json('data'));

        foreach ($deliveryOptions as $expectedDeliveryOption) {
            $match = $actualDataItems->first(function ($actualItem) use ($expectedDeliveryOption) {
                return $actualItem['id'] === $expectedDeliveryOption->id;
            });
            expect($match)->not->toBeNull("Expected PDO with id '{$expectedDeliveryOption->id}' not found or properties mismatch.");

            if ($match) {
                AssertableJson::fromArray($match)
                    ->where('sku', $expectedDeliveryOption->sku)
                    ->where('id', $expectedDeliveryOption->id)
                    ->where('name', $expectedDeliveryOption->name)
                    ->where('fulfillment_type.value', $expectedDeliveryOption->fulfillment_type->value)
                    ->where('fulfillment_type.label', $expectedDeliveryOption->fulfillment_type->translate())
                    ->where('delivery_method.value', $expectedDeliveryOption->delivery_method->value)
                    ->where('delivery_method.label', $expectedDeliveryOption->delivery_method->translate())
                    ->where('price', $expectedDeliveryOption->price)
                    ->where('capacity', $expectedDeliveryOption->capacity)
                    ->where('status.value', $expectedDeliveryOption->status->value)
                    ->where('status.label', $expectedDeliveryOption->status->translate())
                    ->where('is_prepayment_available', $expectedDeliveryOption->is_prepayment_available)
                    ->where('prepayment_amount', $expectedDeliveryOption->prepayment_amount)
                    ->where('is_featured', $expectedDeliveryOption->is_featured)
                    ->where('featured_price', $expectedDeliveryOption->featured_price)
                    ->where('featured_price_start_date',
                        $this->toJalalitString($expectedDeliveryOption->featured_price_start_date))
                    ->where('featured_price_end_date',
                        $this->toJalalitString($expectedDeliveryOption->featured_price_end_date))
                    ->where('created_at', $this->toJalalitString($expectedDeliveryOption->created_at))
                    ->where('updated_at', $this->toJalalitString($expectedDeliveryOption->updated_at))
                    ->where('teachers',
                        $expectedDeliveryOption->teachers?->map(fn (App\Models\Teacher $teacher): array => [
                            'id'         => $teacher->id,
                            'first_name' => $teacher->first_name,
                            'last_name'  => $teacher->last_name,
                            'rate'       => (float) $teacher->rate,
                            'email'      => $teacher->email,
                            'phone'      => $teacher->phone,
                            'gender'     => [
                                'value' => $teacher->gender->value,
                                'label' => $teacher->gender->translate(),
                            ],
                            'birth_date'   => $this->toJalalitString($teacher->birth_date, 'Y-m-d'),
                            'social_links' => $teacher->social_links,
                            'user'         => null,
                        ]))
                    ->etc();
            }
        }
    });

    it('should create a new delivery option for a product', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);

        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $this->product->id]),
            $this->simpleData);

        $response->assertCreated()
            ->assertJsonFragment(['name' => $this->simpleData['name']]);

        $this->assertDatabaseHas('product_delivery_options', [
            'product_id'       => $this->product->id,
            'name'             => $this->simpleData['name'],
            'sku'              => $this->simpleData['sku'],
            'fulfillment_type' => $this->simpleData['fulfillment_type'],
            'delivery_method'  => $this->simpleData['delivery_method'],
            'price'            => $this->simpleData['price'],
            'capacity'         => $this->simpleData['capacity'],
        ]);
        $this->assertDatabaseHas('product_delivery_option_teacher', [
            'product_delivery_option_id' => $response->json('data.id'),
            'teacher_id'                 => $this->teachers[0]->id,
        ]);
        $this->assertDatabaseHas('product_delivery_option_teacher', [
            'product_delivery_option_id' => $response->json('data.id'),
            'teacher_id'                 => $this->teachers[1]->id,
        ]);
        $this->assertDatabaseHas('product_delivery_option_teacher', [
            'product_delivery_option_id' => $response->json('data.id'),
            'teacher_id'                 => $this->teachers[2]->id,
        ]);
    });
    it('should return the specified delivery option details', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_VIEW,
        ]);
        $deliveryOption = ProductDeliveryOption::factory()
            ->withTeachers(3, true)
            ->create();

        $response = $this->getJson(route('api.v1.admin.delivery-option.show',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]));

        $response->assertOk();
        $response->assertJson(function (AssertableJson $json) use ($deliveryOption): void {
            $json->where('data.sku', $deliveryOption->sku)
                ->where('data.id', $deliveryOption->id)
                ->where('data.name', $deliveryOption->name)
                ->where('data.fulfillment_type.value', $deliveryOption->fulfillment_type->value)
                ->where('data.fulfillment_type.label', $deliveryOption->fulfillment_type->translate())
                ->where('data.delivery_method.value', $deliveryOption->delivery_method->value)
                ->where('data.delivery_method.label', $deliveryOption->delivery_method->translate())
                ->where('data.price', $deliveryOption->price)
                ->where('data.capacity', $deliveryOption->capacity)
                ->where('data.status.value', $deliveryOption->status->value)
                ->where('data.status.label', $deliveryOption->status->translate())
                ->where('data.is_prepayment_available', $deliveryOption->is_prepayment_available)
                ->where('data.prepayment_amount', $deliveryOption->prepayment_amount)
                ->where('data.is_featured', $deliveryOption->is_featured)
                ->where('data.featured_price', $deliveryOption->featured_price)
                ->where('data.featured_price_start_date',
                    $this->toJalalitString($deliveryOption->featured_price_start_date))
                ->where('data.featured_price_end_date',
                    $this->toJalalitString($deliveryOption->featured_price_end_date))
                ->where('data.created_at', $this->toJalalitString($deliveryOption->created_at))
                ->where('data.updated_at', $this->toJalalitString($deliveryOption->updated_at))
                ->where('data.teachers',
                    $deliveryOption->teachers?->map(fn (App\Models\Teacher $teacher): array => [
                        'id'         => $teacher->id,
                        'first_name' => $teacher->first_name,
                        'last_name'  => $teacher->last_name,
                        'rate'       => $teacher->rate,
                        'email'      => $teacher->email,
                        'phone'      => $teacher->phone,
                        'gender'     => [
                            'value' => $teacher->gender->value,
                            'label' => $teacher->gender->translate(),
                        ],
                        'birth_date'   => $this->toJalalitString($teacher->birth_date, 'Y-m-d'),
                        'social_links' => $teacher->social_links,
                        'user'         => null,
                    ]))
                ->etc();
        });
    });
    it('should update the specified delivery option', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE,
        ]);
        $deliveryOption = ProductDeliveryOption::factory()->create(
            [
                'product_id'       => $this->product->id,
                'fulfillment_type' => \App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => \App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
            ]
        )->fresh();
        $data            = $deliveryOption->toArray();
        $data['name']    = $this->simpleData['name'];
        $data['details'] = [
            'course_idnumber' => 'course-id-123',
            'activity_id'     => null,
        ];
        $newTeachers      = App\Models\Teacher::factory(2)->create();
        $data['teachers'] = $newTeachers->pluck('id')->toArray();

        $response = $this->putJson(route('api.v1.admin.delivery-option.update',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]), $data);

        $response->assertOk()
            ->assertJsonFragment(['id' => $deliveryOption->id, 'name' => $data['name']]);
        $this->assertDatabaseHas('product_delivery_options', [
            'id'               => $deliveryOption->id,
            'product_id'       => $this->product->id,
            'name'             => $data['name'],
            'sku'              => $deliveryOption->sku,
            'fulfillment_type' => $deliveryOption->fulfillment_type,
            'delivery_method'  => $deliveryOption->delivery_method,
            'price'            => $deliveryOption->price,
            'capacity'         => $deliveryOption->capacity,
        ]);
        $this->assertDatabaseHas('product_delivery_option_teacher', [
            'product_delivery_option_id' => $deliveryOption->id,
            'teacher_id'                 => $newTeachers[0]->id,
        ]);
        $this->assertDatabaseHas('product_delivery_option_teacher', [
            'product_delivery_option_id' => $deliveryOption->id,
            'teacher_id'                 => $newTeachers[1]->id,
        ]);

    });
    it('should delete the specified delivery option', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_DELETE,
        ]);
        $deliveryOption = ProductDeliveryOption::factory()->create();

        $response = $this->deleteJson(route('api.v1.admin.delivery-option.destroy',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]));

        $response->assertNoContent();
    });
});
describe('User without permissions', function (): void {
    beforeEach(function (): void {
        $this->product    = App\Models\Product::factory()->create();
        $this->simpleData = ProductDeliveryOption::factory()
            ->make(
                [
                    'product_id'       => $this->product->id,
                    'fulfillment_type' => \App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method'  => \App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
                ]
            )->toArray();
        $this->simpleData['details'] = [
            'course_idnumber' => 'course-id-123',
            'activity_id'     => null,
        ];
        $this->teachers               = App\Models\Teacher::factory()->count(3)->create();
        $this->simpleData['teachers'] = $this->teachers->pluck('id')->toArray();
        $this->unauthorized_user();
    });
    it('should return 403 if user does not have permission to view delivery options', function (): void {

        $product = App\Models\Product::factory()->create();

        $response = $this->getJson(route('api.v1.admin.delivery-option.index', ['product' => $product->id]));

        $response->assertForbidden();
    });
    it('should return 403 if user does not have permission to create delivery options', function (): void {

        $product  = App\Models\Product::factory()->create();
        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]),
            $this->simpleData);

        $response->assertForbidden();
    });
    it('should return 403 if user does not have permission to update delivery options', function (): void {

        $deliveryOption = ProductDeliveryOption::factory()->create(
            [
                'product_id'       => $this->product->id,
                'fulfillment_type' => \App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => \App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
            ]
        );
        $response = $this->putJson(route('api.v1.admin.delivery-option.update',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]), $this->simpleData);

        $response->assertForbidden();
    });
    it('should return 403 if user does not have permission to delete delivery options', function (): void {

        $deliveryOption = ProductDeliveryOption::factory()->create();

        $response = $this->deleteJson(route('api.v1.admin.delivery-option.destroy',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]));

        $response->assertForbidden();
    });

});

describe('validation', function (): void {
    it('should return validation error for required fields', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);
        $product = App\Models\Product::factory()->create();
        $data    = ProductDeliveryOption::factory()
            ->make(['product_id' => $product->id, 'name' => null])->toArray();

        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
    it('should return error if delivery option doesn\'t belong to fulfillment type', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);
        $product = App\Models\Product::factory()->create();
        $data    = ProductDeliveryOption::factory()
            ->make([
                'product_id'       => $product->id,
                'fulfillment_type' => \App\Enums\Product\FulfillmentTypeEnum::OFFLINE_SERVICE->value,
                'delivery_method'  => \App\Enums\Product\DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
            ])->toArray();
        $data['details'] = [
            'file_id' => 'file-id-123',
        ];
        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_method']);
    });

    it('should return correct validation errors for each delivery option details', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);
        $product = App\Models\Product::factory()->create();
        $data    = ProductDeliveryOption::factory()
            ->make([
                'product_id'       => $product->id,
                'fulfillment_type' => \App\Enums\Product\FulfillmentTypeEnum::DIGITAL->value,
                'delivery_method'  => \App\Enums\Product\DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
            ])->toArray();
        $data['details'] = [];

        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.max_downloads']);

        $data['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE->value;
        $data['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE->value;

        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.course_idnumber']);

        $data['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE->value;
        $data['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::LIVE_SESSION_BBB->value;

        $response = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details']);

        $data['delivery_method'] = \App\Enums\Product\DeliveryMethodEnum::LIVE_SESSION_SKYROOM->value;
        $response                = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.meeting_name_identifier']);

        $data['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::OFFLINE_SERVICE->value;
        $data['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->value;
        $response                 = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.course_id']);

        $data['fulfillment_type'] = \App\Enums\Product\FulfillmentTypeEnum::IN_PERSON_SERVICE->value;
        $data['delivery_method']  = \App\Enums\Product\DeliveryMethodEnum::IN_PERSON->value;
        $response                 = $this->postJson(route('api.v1.admin.delivery-option.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(
                [
                    'details.location',
                    'details.duration',
                    'details.schedule',
                ]);
    });
});
