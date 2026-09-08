<?php

declare(strict_types=1);

use App\Models\Bundle;
use App\Models\ProductDeliveryOption;
use App\Models\Teacher;
use Illuminate\Testing\Fluent\AssertableJson;

uses(Tests\Support\Traits\AuthTestTrait::class);
describe('User with permissions', function (): void {
    beforeEach(function (): void {
        $this->product    = App\Models\Product::factory()->create();
        $this->simpleData = ProductDeliveryOption::factory()
            ->make(
                [
                    'product_id'       => $this->product->id,
                    'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
                    'details_json'     => [
                        'lm',
                    ],
                    'access_days' => 12,
                ]
            )->toArray();
        $this->simpleData['details'] = [
            'moodle_course_id' => 120,
            'ims_course_code'  => 'course-id-123',
            'activity_id'      => null,
        ];
        $this->teachers               = Teacher::factory()->count(3)->create();
        $this->simpleData['teachers'] = $this->teachers->pluck('id')->toArray();
    });
    it('should return a list of delivery options for a product', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_VIEW_ANY,
        ]);
        $product = App\Models\Product::factory()->create();
        ProductDeliveryOption::factory()
            ->withTeachers(3, true)
            ->count(3)
            ->create(['product_id' => $product->id]);
        $deliveryOptions = ProductDeliveryOption::query()
            ->with('teachers', fn ($q) => $q->orderBy('id'))
            ->get();
        $response = $this->getJson(route('api.v1.admin.delivery-options.index', ['product' => $product->id]));
        $response->assertOk()
            ->assertJsonCount(3, 'data');
        $actualDataItems = collect($response->json('data'));
        foreach ($deliveryOptions as $expectedDeliveryOption) {
            $match = $actualDataItems->first(function (array $actualItem) use ($expectedDeliveryOption): bool {
                return $actualItem['id'] === $expectedDeliveryOption->id;
            });
            expect($match)->not->toBeNull("Expected PDO with id '{$expectedDeliveryOption->id}' not found or properties mismatch.");

            $teachers = data_get($match, 'teachers');
            expect(count($teachers))->toBe($expectedDeliveryOption->teachers->count());
            foreach ($teachers as $teacherData) {
                $expectedTeacher = $expectedDeliveryOption->teachers->firstWhere('id', $teacherData['id']);
                expect($teacherData['id'])->toBe($expectedTeacher->id)
                    ->and($teacherData['first_name'])->toBe($expectedTeacher->first_name)
                    ->and($teacherData['last_name'])->toBe($expectedTeacher->last_name)
                    ->and($teacherData['avatar_url'])->toBe($expectedTeacher->avatar_url)
                    ->and((float) $teacherData['rate'])->toBe((float) $expectedTeacher->rate)
                    ->and($teacherData['email'])->toBe($expectedTeacher->email)
                    ->and($teacherData['phone'])->toBe($expectedTeacher->phone)
                    ->and($teacherData['gender']['value'])->toBe($expectedTeacher->gender->value)
                    ->and($teacherData['gender']['label'])->toBe($expectedTeacher->gender->translate())
                    ->and($teacherData['birth_date'])->toBe($this->toJalalitString($expectedTeacher->birth_date, 'Y-m-d'))
                    ->and($teacherData['social_links'])->toBe($expectedTeacher->social_links)
                    ->and($teacherData['user'])->toBe(null);
            }
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
                    ->where('registration_start_date',
                        $this->toJalalitString($expectedDeliveryOption->registration_start_date))
                    ->where('registration_end_date',
                        $this->toJalalitString($expectedDeliveryOption->registration_end_date))
                    ->where('available_from',
                        $this->toJalalitString($expectedDeliveryOption->available_from))
                    ->where('available_to',
                        $this->toJalalitString($expectedDeliveryOption->available_to))
                    ->where('access_days',
                        $this->toJalalitString($expectedDeliveryOption->access_days))
                    ->where('created_at', $this->toJalalitString($expectedDeliveryOption->created_at))
                    ->where('updated_at', $this->toJalalitString($expectedDeliveryOption->updated_at))
                    ->etc();
            }
        }
    });

    it('should create a new delivery option for a product', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);

        $response = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $this->product->id]),
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

    it('should create a composite delivery option for a Bundle product', function (): void {
        $bundle        = Bundle::factory()->create();
        $bundleProduct = App\Models\Product::factory()->create([
            'productable_type' => App\Enums\Product\ProductableEnum::BUNDLE->value,
            'productable_id'   => $bundle->id,
        ]);
        $component = ProductDeliveryOption::factory()->create();
        $this->authorized_user([App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE]);

        $response = $this->postJson(
            route('api.v1.admin.delivery-options.store', ['product' => $bundleProduct->id]),
            [
                'name'                    => 'Bundle Option',
                'price'                   => $component->price,
                'status'                  => 'published',
                'details'                 => [],
                'is_featured'             => false,
                'components'              => [[
                    'product_delivery_option_id' => $component->id,
                    'allocation'                 => $component->price,
                ]],
            ]
        );

        $response->assertCreated();
        $this->assertDatabaseHas('product_delivery_options', [
            'id'                      => $response->json('data.id'),
            'product_id'              => $bundleProduct->id,
            'fulfillment_type'        => 'composite',
            'delivery_method'         => 'bundle',
            'is_prepayment_available' => false,
            'prepayment_amount'       => null,
        ]);
        $this->assertDatabaseHas('bundle_components', [
            'bundle_product_delivery_option_id'    => $response->json('data.id'),
            'component_product_delivery_option_id' => $component->id,
            'allocation'                           => $component->price,
        ]);
    });

    it('should reject fulfilment fields on a Bundle delivery option', function (): void {
        $bundle        = Bundle::factory()->create();
        $bundleProduct = App\Models\Product::factory()->create([
            'productable_type' => App\Enums\Product\ProductableEnum::BUNDLE->value,
            'productable_id'   => $bundle->id,
        ]);
        $component = ProductDeliveryOption::factory()->create();
        $this->authorized_user([App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE]);

        $this->postJson(
            route('api.v1.admin.delivery-options.store', ['product' => $bundleProduct->id]),
            [
                'name'                    => 'Invalid Bundle Option',
                'fulfillment_type'        => 'online_service',
                'delivery_method'         => 'lms_moodle',
                'price'                   => $component->price,
                'status'                  => 'published',
                'details'                 => [],
                'is_prepayment_available' => false,
                'is_featured'             => false,
                'components'              => [[
                    'product_delivery_option_id' => $component->id,
                    'allocation'                 => $component->price,
                ]],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fulfillment_type', 'delivery_method']);
    });

    it('prohibits teachers on Bundle delivery option requests', function (): void {
        $bundle        = Bundle::factory()->create();
        $bundleProduct = App\Models\Product::factory()->create([
            'productable_type' => App\Enums\Product\ProductableEnum::BUNDLE->value,
            'productable_id'   => $bundle->id,
        ]);
        $component = ProductDeliveryOption::factory()->create();
        $this->authorized_user([App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE]);

        $this->postJson(
            route('api.v1.admin.delivery-options.store', ['product' => $bundleProduct->id]),
            [
                'name'                    => 'Invalid Teacher Bundle Option',
                'price'                   => $component->price,
                'status'                  => 'published',
                'details'                 => [],
                'teachers'                => [999999],
                'is_prepayment_available' => false,
                'is_featured'             => false,
                'components'              => [[
                    'product_delivery_option_id' => $component->id,
                    'allocation'                 => $component->price,
                ]],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['teachers']);
    });
    it('should store detail dates as Gregorian and return them as Jalali', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);

        $jalaliDate                                = '1405-05-19';
        $gregorianDate                             = Hekmatinasser\Verta\Facades\Verta::parseFormat('Y-m-d', $jalaliDate)->toCarbon()->format('Y-m-d');
        $this->simpleData['details']['start_date'] = $jalaliDate;

        $response = $this->postJson(
            route('api.v1.admin.delivery-options.store', ['product' => $this->product->id]),
            $this->simpleData
        );

        $response->assertCreated()
            ->assertJsonPath('data.details.start_date', $jalaliDate);

        $deliveryOption = ProductDeliveryOption::query()->findOrFail($response->json('data.id'));

        expect($deliveryOption->details_json['start_date'])->toBe($gregorianDate);
    });
    it('should create a new delivery option for a product without providing sku', function (?string $sku): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);
        if ($sku !== null) {
            $this->simpleData['sku'] = $sku;
        } else {
            unset($this->simpleData['sku']);
        }

        $response = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $this->product->id]),
            $this->simpleData);

        $response->assertCreated()
            ->assertJsonFragment(['name' => $this->simpleData['name']]);

        $this->assertDatabaseHas('product_delivery_options', [
            'product_id'       => $this->product->id,
            'name'             => $this->simpleData['name'],
            'sku'              => $response->json('data.sku'),
            'fulfillment_type' => $this->simpleData['fulfillment_type'],
            'delivery_method'  => $this->simpleData['delivery_method'],
            'price'            => $this->simpleData['price'],
            'capacity'         => $this->simpleData['capacity'],
        ]);
    })->with([
        [''],
        [null],
    ]);
    it('should return the specified delivery option details', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_VIEW,
        ]);
        $deliveryOption = ProductDeliveryOption::factory()
            ->withTeachers(3, true)
            ->create();

        $response = $this->getJson(route('api.v1.admin.delivery-options.show',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]));

        $response->assertOk();
        $teachers = $response->json('data.teachers');
        expect(count($teachers))->toBe(3);
        foreach ($teachers as $teacherData) {
            $expectedTeacher = $deliveryOption->teachers->firstWhere('id', $teacherData['id']);
            expect($teacherData['id'])->toBe($expectedTeacher->id)
                ->and($teacherData['first_name'])->toBe($expectedTeacher->first_name)
                ->and($teacherData['last_name'])->toBe($expectedTeacher->last_name)
                ->and((float) $teacherData['rate'])->toBe((float) $expectedTeacher->rate)
                ->and($teacherData['avatar_url'])->toBe($expectedTeacher->avatar_url)
                ->and($teacherData['email'])->toBe($expectedTeacher->email)
                ->and($teacherData['phone'])->toBe($expectedTeacher->phone)
                ->and($teacherData['gender']['value'])->toBe($expectedTeacher->gender->value)
                ->and($teacherData['gender']['label'])->toBe($expectedTeacher->gender->translate())
                ->and($teacherData['birth_date'])->toBe($this->toJalalitString($expectedTeacher->birth_date, 'Y-m-d'))
                ->and($teacherData['social_links'])->toBe($expectedTeacher->social_links)
                ->and($teacherData['user'])->toBe(null);
        }

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
                ->where('data.registration_start_date',
                    $this->toJalalitString($deliveryOption->registration_start_date))
                ->where('data.registration_end_date',
                    $this->toJalalitString($deliveryOption->registration_end_date))
                ->where('data.available_from',
                    $this->toJalalitString($deliveryOption->available_from))
                ->where('data.available_to',
                    $this->toJalalitString($deliveryOption->available_to))
                ->where('data.access_days',
                    $this->toJalalitString($deliveryOption->access_days))
                ->where('data.created_at', $this->toJalalitString($deliveryOption->created_at))
                ->where('data.updated_at', $this->toJalalitString($deliveryOption->updated_at))
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
                'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
            ]
        )->fresh();
        $data            = $deliveryOption->toArray();
        $data['name']    = $this->simpleData['name'];
        $data['details'] = [
            'moodle_course_id' => 120,
            'ims_course_code'  => 'course-id-123',
            'activity_id'      => null,
        ];
        $newTeachers      = Teacher::factory(2)->create();
        $data['teachers'] = $newTeachers->pluck('id')->toArray();

        $response = $this->putJson(route('api.v1.admin.delivery-options.update',
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
            'access_days'      => $deliveryOption->access_days,
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

    it('should update a Bundle composite delivery option', function (): void {
        $bundle        = Bundle::factory()->create();
        $bundleProduct = App\Models\Product::factory()->create([
            'productable_type' => App\Enums\Product\ProductableEnum::BUNDLE->value,
            'productable_id'   => $bundle->id,
        ]);
        $component      = ProductDeliveryOption::factory()->create();
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'       => $bundleProduct->id,
            'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::COMPOSITE,
            'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::BUNDLE,
            'is_prepayment_available' => false,
            'prepayment_amount' => null,
            'price'            => $component->price,
            'details_json'     => [],
        ]);
        $deliveryOption->bundleComponents()->attach($component->id, ['allocation' => $component->price]);
        $this->authorized_user([App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE]);

        $response = $this->putJson(
            route('api.v1.admin.delivery-options.update', [
                'product'         => $bundleProduct->id,
                'delivery_option' => $deliveryOption->id,
            ]),
            [
                'name'                      => 'Updated Bundle Option',
                'sku'                       => $deliveryOption->sku,
                'price'                     => $component->price,
                'status'                    => 'published',
                'details'                   => [],
                'capacity'                  => null,
                'is_featured'               => false,
                'featured_price'            => null,
                'featured_price_start_date' => null,
                'featured_price_end_date'   => null,
                'registration_start_date'   => null,
                'registration_end_date'     => null,
                'available_from'            => null,
                'available_to'              => null,
                'access_days'               => null,
                'components'                => [[
                    'product_delivery_option_id' => $component->id,
                    'allocation'                 => $component->price,
                ]],
            ]
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas('product_delivery_options', [
            'id'                      => $deliveryOption->id,
            'name'                    => 'Updated Bundle Option',
            'is_prepayment_available' => false,
            'prepayment_amount'       => null,
        ]);
    });
    it('should convert detail dates when updating a delivery option', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_UPDATE,
        ]);
        $deliveryOption = ProductDeliveryOption::factory()->create([
            'product_id'       => $this->product->id,
            'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
            'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
        ]);
        $data             = $deliveryOption->toArray();
        $jalaliDate       = '1405-05-19';
        $jalaliEndDate    = '1405-05-20';
        $gregorianDate    = Hekmatinasser\Verta\Facades\Verta::parseFormat('Y-m-d', $jalaliDate)->toCarbon()->format('Y-m-d');
        $gregorianEndDate = Hekmatinasser\Verta\Facades\Verta::parseFormat('Y-m-d', $jalaliEndDate)->toCarbon()->format('Y-m-d');
        $data['details']  = [
            'moodle_course_id'      => 120,
            'activity_id'           => null,
            'start_date'            => $jalaliDate,
            'enrollment_start_date' => $jalaliDate,
            'enrollment_end_date'   => $jalaliEndDate,
        ];
        $data['teachers'] = Teacher::factory()->count(2)->create()->pluck('id')->all();

        $response = $this->putJson(
            route('api.v1.admin.delivery-options.update', [
                'product'         => $deliveryOption->product_id,
                'delivery_option' => $deliveryOption->id,
            ]),
            $data
        );

        $response->assertOk()
            ->assertJsonPath('data.details.start_date', $jalaliDate)
            ->assertJsonPath('data.details.enrollment_start_date', $jalaliDate)
            ->assertJsonPath('data.details.enrollment_end_date', $jalaliEndDate);

        $details = $deliveryOption->fresh()->details_json;

        expect($details['start_date'])->toBe($gregorianDate)
            ->and($details['enrollment_start_date'])->toBe($gregorianDate)
            ->and($details['enrollment_end_date'])->toBe($gregorianEndDate);
    });
    it('should delete the specified delivery option', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_DELETE,
        ]);
        $deliveryOption = ProductDeliveryOption::factory()->create();

        $response = $this->deleteJson(route('api.v1.admin.delivery-options.destroy',
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
                    'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
                    'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
                ]
            )->toArray();
        $this->simpleData['details'] = [
            'moodle_course_id' => 120,
            'ims_course_code'  => 'course-id-123',
            'activity_id'      => null,
        ];
        $this->teachers               = Teacher::factory()->count(3)->create();
        $this->simpleData['teachers'] = $this->teachers->pluck('id')->toArray();
        $this->unauthorized_user();
    });
    it('should return 403 if user does not have permission to view delivery options', function (): void {

        $product = App\Models\Product::factory()->create();

        $response = $this->getJson(route('api.v1.admin.delivery-options.index', ['product' => $product->id]));

        $response->assertForbidden();
    });
    it('should return 403 if user does not have permission to create delivery options', function (): void {

        $product  = App\Models\Product::factory()->create();
        $response = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]),
            $this->simpleData);

        $response->assertForbidden();
    });
    it('should return 403 if user does not have permission to update delivery options', function (): void {

        $deliveryOption = ProductDeliveryOption::factory()->create(
            [
                'product_id'       => $this->product->id,
                'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
                'details_json'     => [
                    'moodle_course_id' => 120,
                ],
            ]
        );
        $response = $this->putJson(route('api.v1.admin.delivery-options.update',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]), $this->simpleData);

        $response->assertForbidden();
    });
    it('should return 403 if user does not have permission to delete delivery options', function (): void {

        $deliveryOption = ProductDeliveryOption::factory()->create();

        $response = $this->deleteJson(route('api.v1.admin.delivery-options.destroy',
            ['product' => $deliveryOption->product_id, 'delivery_option' => $deliveryOption->id]));

        $response->assertForbidden();
    });

});

describe('validation', function (): void {
    it('distinguishes an invalid Jalali date from an invalid date format', function (): void {
        $this->app->setLocale('fa');
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);
        $product = App\Models\Product::factory()->create();
        $teacher = Teacher::factory()->create();
        $data    = ProductDeliveryOption::factory()
            ->make([
                'product_id'       => $product->id,
                'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE,
                'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE,
            ])->toArray();
        $data['teachers'] = [$teacher->id];
        $data['details']  = [
            'moodle_course_id' => 120,
            'activity_id'      => null,
        ];
        $data['available_from'] = '1405-12-31';

        $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data)
            ->assertUnprocessable()
            ->assertJsonPath('errors.available_from.0', 'تاریخ انتشار یک تاریخ جلالی معتبر نیست.');

        $data['available_from'] = '1405-12';

        $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data)
            ->assertUnprocessable()
            ->assertJsonPath('errors.available_from.0', 'تاریخ انتشار با فرمت Y-m-d مطابقت ندارد.');
    });

    it('should return validation error for required fields', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);
        $product = App\Models\Product::factory()->create();
        $data    = ProductDeliveryOption::factory()
            ->make(['product_id' => $product->id, 'name' => null])->toArray();

        $response = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data);

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
                'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::OFFLINE_SERVICE->value,
                'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
            ])->toArray();
        $data['details'] = [
            'file_id' => 'file-id-123',
        ];
        $response = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_method']);
    });

    it('should return correct validation errors for each delivery option details', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_DELIVERY_OPTION_CREATE,
        ]);
        $product = App\Models\Product::factory()->create();
        $teacher = Teacher::factory()->create();
        $data    = ProductDeliveryOption::factory()
            ->make([
                'product_id'       => $product->id,
                'fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::DIGITAL->value,
                'delivery_method'  => App\Enums\Product\DeliveryMethodEnum::DIRECT_DOWNLOAD->value,
            ])->toArray();
        $data['teachers'] = [$teacher->id];
        $data['details']  = [];

        $response = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.max_downloads']);

        $data['fulfillment_type'] = App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE->value;
        $data['delivery_method']  = App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE->value;

        $response = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.moodle_course_id']);

        $data['fulfillment_type'] = App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE->value;
        $data['delivery_method']  = App\Enums\Product\DeliveryMethodEnum::LMS_MOODLE->value;

        $response = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details']);

        $data['delivery_method'] = App\Enums\Product\DeliveryMethodEnum::LIVE_SESSION_SKYROOM->value;
        $response                = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details']);

        $data['fulfillment_type'] = App\Enums\Product\FulfillmentTypeEnum::OFFLINE_SERVICE->value;
        $data['delivery_method']  = App\Enums\Product\DeliveryMethodEnum::VIDEO_PLATFORM_SPOTPLAYER->value;
        $response                 = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['details.spot_id']);

        $data['fulfillment_type'] = App\Enums\Product\FulfillmentTypeEnum::IN_PERSON_SERVICE->value;
        $data['delivery_method']  = App\Enums\Product\DeliveryMethodEnum::IN_PERSON->value;
        $response                 = $this->postJson(route('api.v1.admin.delivery-options.store', ['product' => $product->id]), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(
                [
                    'details.address',
                ]);
    });
});
