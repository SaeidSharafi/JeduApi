<?php

declare(strict_types=1);

use App\Actions\Shop\Student\ListStudentEnrollmentsAction;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\ProductableEnum;
use App\Http\Controllers\Api\Shop\Student\SeminarController;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\User;

uses(Tests\Support\Traits\AuthTestTrait::class);

covers(ListStudentEnrollmentsAction::class, SeminarController::class);

beforeEach(function (): void {
    $this->customer();
});

it('lists only seminar enrollments', function (): void {
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
    createEnrollment($this->user, DeliveryMethodEnum::DIRECT_DOWNLOAD, productableType: ProductableEnum::DIGITAL_ASSET);
    $seminarEnrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, productableType: ProductableEnum::SEMINAR);

    $this->getJson(route('api.v1.shop.student.seminars.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.uuid', $seminarEnrollment->uuid);
});

it('should filter by product name', function (): void {
    $product = Product::factory()->withSeminar()->create([
        'name' => 'Named Seminar',
    ]);
    $deliveryOption = ProductDeliveryOption::factory()->create([
        'name'             => 'Named Seminar',
        'delivery_method'  => DeliveryMethodEnum::LIVE_SESSION_NILIROOM->value,
        'fulfillment_type' => DeliveryMethodEnum::LIVE_SESSION_NILIROOM->getFulfillmentType(),
        'product_id'       => $product->id,
    ]);
    createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, productableType: ProductableEnum::SEMINAR);
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, deliveryOption: $deliveryOption);

    $this->getJson(route('api.v1.shop.student.seminars.index', [
        'filter' => ['name' => 'Named Seminar'],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.uuid', $enrollment->uuid)
        ->assertJsonPath('data.data.0.product.name', 'Named Seminar');
});

it('shows a seminar enrollment detail', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, productableType: ProductableEnum::SEMINAR);

    $this->getJson(route('api.v1.shop.student.seminars.show', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.uuid', $enrollment->uuid);
});

it('returns 404 when showing a course enrollment on the seminars route', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);

    $this->getJson(route('api.v1.shop.student.seminars.show', ['enrollment' => $enrollment->uuid]))
        ->assertNotFound()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);
});

it('does not show other users seminar enrollment details', function (): void {
    $other      = User::factory()->create();
    $enrollment = createEnrollment($other, DeliveryMethodEnum::LIVE_SESSION_NILIROOM, productableType: ProductableEnum::SEMINAR);

    $this->getJson(route('api.v1.shop.student.seminars.show', ['enrollment' => $enrollment->uuid]))
        ->assertNotFound()
        ->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);
});
