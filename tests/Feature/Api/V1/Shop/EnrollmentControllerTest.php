<?php

declare(strict_types=1);

use App\Contracts\Integrations\BbbClientContract;
use App\Enums\Product\DeliveryMethodEnum;
use App\Models\ProductDeliveryOption;

use function Pest\Laravel\getJson;

uses(Tests\Support\Traits\AuthTestTrait::class);
beforeEach(function (): void {
    $this->customer();
});
it('should filter by fulfillment type', function (): void {
    createEnrollment($this->user, DeliveryMethodEnum::IN_PERSON, 2);
    createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);
    $this->getJson(route('api.v1.shop.student.courses.index', [
        'filter' => ['fulfillment_type' => App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE->value],
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.product.fulfillment_type.value',
            App\Enums\Product\FulfillmentTypeEnum::ONLINE_SERVICE->value);
});
it('should filter by product name', function (): void {
    $product = App\Models\Product::factory()->create([
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
        ->assertJsonPath('data.total', 5); // Assuming there are 3 enrollments in total
});
it('shows current user specific enrollment details', function (): void {
    $product = App\Models\Product::factory()->create([
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
it('does not show other users enrollment details', function (): void {

    $user       = App\Models\User::factory()->create()->fresh();
    $enrollment = createEnrollment($user, DeliveryMethodEnum::LMS_MOODLE);
    $response   = $this->getJson(route('api.v1.shop.student.courses.show', [
        'enrollment' => $enrollment->uuid,
        'per_page'   => 1,
    ]));

    $response->assertNotFound();
    $response->assertJsonFragment(['message' => __('messages.enrollments.not_found')]);

});

// ─── Join URL ─────────────────────────────────────────────────────────────────

it('returns join url for bbb enrollment', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LIVE_SESSION_BBB);
    $enrollment->forceFill([
        'provisioning_data' => [
            'providers' => [
                'bbb' => [
                    'status' => 'completed',
                    'data'   => ['meeting_id' => 'test-meeting-123'],
                ],
            ],
        ],
    ])->saveQuietly();

    $this->mock(BbbClientContract::class, function ($mock): void {
        $mock->shouldReceive('buildJoinUrl')->andReturn('https://bbb.test/join/abc');
    });

    getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertOk()
        ->assertJsonPath('data.url', 'https://bbb.test/join/abc');
});

it('returns 404 for join url when enrollment belongs to another user', function (): void {
    $otherUser  = App\Models\User::factory()->create();
    $enrollment = createEnrollment($otherUser, DeliveryMethodEnum::LIVE_SESSION_BBB);

    getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertNotFound();
});

it('returns 422 for join url when delivery method does not support it', function (): void {
    $enrollment = createEnrollment($this->user, DeliveryMethodEnum::LMS_MOODLE);

    getJson(route('api.v1.shop.student.courses.join', ['enrollment' => $enrollment->uuid]))
        ->assertUnprocessable();
});
