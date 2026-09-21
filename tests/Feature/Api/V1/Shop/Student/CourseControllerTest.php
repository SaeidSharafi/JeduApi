<?php

declare(strict_types=1);

use App\Actions\Shop\Student\ListStudentEnrollmentsAction;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Api\Shop\Student\CourseController;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(ListStudentEnrollmentsAction::class, CourseController::class);

beforeEach(function (): void {
    $this->customer();
});

it('lists only course enrollments', function (): void {
    $courseEnrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
    createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, productableType: ProductableEnum::SEMINAR);
    createEnrollment($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, productableType: ProductableEnum::DIGITAL_ASSET);

    $this->getJson(route('api.v1.shop.student.courses.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.uuid', $courseEnrollment->uuid);
});

it('should filter by fulfillment type', function (): void {
    createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON, 2);
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
    $this->getJson(route('api.v1.shop.student.courses.index', [
        'filter' => ['fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE->value],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.product.fulfillment_type.value',
            FulfillmentTypeEnum::ONLINE_SERVICE->value);
});

it('should filter by product name', function (): void {
    $product = Product::factory()->withCourse()->create([
        'name' => 'Test Product',
    ]);
    $deliveryOption = ProductDeliveryOption::factory()
        ->create([
            'name'            => 'Test Product',
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE->value,
            'product_id'      => $product->id,
        ]);
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, 5);
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $this->getJson(route('api.v1.shop.student.courses.index', [
        'filter' => ['name' => 'Test Product'],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.product.name', 'Test Product');
});

it('should paginate results', function (): void {
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, count: 5);
    $this->getJson(route('api.v1.shop.student.courses.index', [
        'per_page' => 1,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.total', 5);
});

it('shows current user specific enrollment details', function (): void {
    $product = Product::factory()->withCourse()->create([
        'name' => 'Test Product',
    ]);
    $deliveryOption = ProductDeliveryOption::factory()
        ->create([
            'name'            => 'Test Product',
            'delivery_method' => DeliveryMethodEnum::LMS_MOODLE->value,
            'product_id'      => $product->id,
        ]);
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, 5);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE, deliveryOption: $deliveryOption);
    $response   = $this->getJson(route('api.v1.shop.student.courses.show', [
        'enrollment' => $enrollment->uuid,
        'per_page'   => 1,
    ]));

    $response->assertOk();
    $response->assertJson(function (Illuminate\Testing\Fluent\AssertableJson $json) use ($enrollment): void {
        $enrollment->load('product');
        $json->where('data.uuid', $enrollment->uuid)
            ->where('data.enrollment_status', [
                'value' => $enrollment->enrollment_status->value,
                'label' => $enrollment->enrollment_status->translate(),
            ])
            ->where('data.product.name', $enrollment->product->name)
            ->etc();
    });

});

it('returns 404 when showing a seminar enrollment on the courses route', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, productableType: ProductableEnum::SEMINAR);

    $this->getJson(route('api.v1.shop.student.courses.show', ['enrollment' => $enrollment->uuid]))
        ->assertNotFound()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);
});

it('does not show other users enrollment details', function (): void {
    $user       = User::factory()->create()->fresh();
    $enrollment = createEnrollment($user, DeliveryMethodEnum::LMS_MOODLE);

    $this->getJson(route('api.v1.shop.student.courses.show', [
        'enrollment' => $enrollment->uuid,
        'per_page'   => 1,
    ]))
        ->assertNotFound()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);
});
