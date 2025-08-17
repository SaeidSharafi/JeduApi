<?php

declare(strict_types=1);

use App\Models\DiscountPromotion;

uses(\Tests\AuthTestTrait::class);

describe('DiscountPromotionController', function (): void {
    beforeEach(function (): void {
        $this->authorized_user([
            \App\Enums\PermissionEnum::DISCOUNT_VIEW_ANY,
            \App\Enums\PermissionEnum::DISCOUNT_VIEW,
            \App\Enums\PermissionEnum::DISCOUNT_CREATE,
            \App\Enums\PermissionEnum::DISCOUNT_UPDATE,
            \App\Enums\PermissionEnum::DISCOUNT_DELETE,
        ]);
    });

    test('index returns paginated promotions list', function (): void {
        // Arrange
        DiscountPromotion::factory()->count(3)->create();

        // Act
        $response = $this->get('/api/v1/admin/discount-promotion');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'type',
                            'is_active',
                            'created_at',
                            'updated_at',
                        ]
                    ],
                ],
            ]);

        expect($response->json('data.data'))->toHaveCount(3);
    });

    test('index filters by active status', function (): void {
        // Arrange
        DiscountPromotion::factory()->create(['is_active' => true]);
        DiscountPromotion::factory()->create(['is_active' => false]);

        // Act
        $response = $this->get('/api/v1/admin/discount-promotion?filter[is_active]=1');

        // Assert
        $response->assertOk();
        expect($response->json('data.data'))->toHaveCount(1)
            ->and($response->json('data.data.0.is_active'))->toBeTrue();
    });

    test('index filters by promotion type', function (): void {
        // Arrange
        DiscountPromotion::factory()->create(['type' => 'cart_checkout']);
        DiscountPromotion::factory()->create(['type' => 'product_specific']);

        // Act
        $response = $this->get('/api/v1/admin/discount-promotion?filter[type]=cart_checkout');

        // Assert
        $response->assertOk();
        expect($response->json('data.data'))->toHaveCount(1)
            ->and($response->json('data.data.0.type.value'))->toBe('cart_checkout');
    });

    test('index searches by name', function (): void {
        // Arrange
        DiscountPromotion::factory()->create(['name' => 'Summer Sale']);
        DiscountPromotion::factory()->create(['name' => 'Winter Discount']);

        // Act
        $response = $this->get('/api/v1/admin/discount-promotion?filter[search]=Summer');

        // Assert
        $response->assertOk();
        expect($response->json('data.data'))->toHaveCount(1)
            ->and($response->json('data.data.0.name'))->toBe('Summer Sale');
    });

    test('store creates new promotion', function (): void {
        // Arrange
        $data = [
            'name'        => 'Test Promotion',
            'description' => 'Test Description',
            'type'        => 'cart_checkout',
            'is_active'   => true,
            'priority'    => 100,
            'rules'       => [
                [
                    'type'          => 'action',
                    'handler'       => 'apply_percentage_off',
                    'configuration' => ['percentage' => 20],
                ],
            ],
            'coupons'     => [
                [
                    'code'      => 'TEST2024',
                    'is_active' => true,
                ],
            ],
        ];

        // Act
        $response = $this->postJson('/api/v1/admin/discount-promotion', $data);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'type',
                    'is_active',
                    'rules',
                    'coupons',
                ],
                'message'
            ]);

        $this->assertDatabaseHas('discount_promotions', [
            'name'        => 'Test Promotion',
            'description' => 'Test Description',
        ]);
    });

    test('store validates required fields', function (): void {
        // Act
        $response = $this->postJson('/api/v1/admin/discount-promotion', []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'rules']);
    });

    test('show returns specific promotion with relations', function (): void {
        // Arrange
        $promotion = DiscountPromotion::factory()->create();
        $promotion->rules()->create([
            'type'          => 'action',
            'handler'       => 'test_handler',
            'configuration' => ['test' => 'config'],
        ]);

        // Act
        $response = $this->get("/api/v1/admin/discount-promotion/{$promotion->id}");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'rules' => [
                        '*' => [
                            'id',
                            'type',
                            'handler',
                            'configuration',
                        ]
                    ],
                    'coupons',
                ]
            ]);
    });

    test('update modifies existing promotion', function (): void {
        // Arrange
        $promotion = DiscountPromotion::factory()->create(['name' => 'Old Name']);

        $updateData = [
            'name'        => 'Updated Name',
            'description' => 'Updated Description',
            'type'        => 'cart_checkout',
            'rules'       => [
                [
                    'type'          => 'action',
                    'handler'       => 'apply_percentage_off',
                    'configuration' => ['percentage' => 25],
                ],
            ],
            'coupons'     => [],
        ];

        // Act
        $response = $this->putJson("/api/v1/admin/discount-promotion/{$promotion->id}", $updateData);

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description'],
                'message'
            ]);

        expect($response->json('data.name'))->toBe('Updated Name');

        $this->assertDatabaseHas('discount_promotions', [
            'id'   => $promotion->id,
            'name' => 'Updated Name',
        ]);
    });

    test('destroy removes promotion', function (): void {
        // Arrange
        $promotion = DiscountPromotion::factory()->create()->fresh();
        // Act
        $response = $this->delete("/api/v1/admin/discount-promotion/{$promotion->id}");

        // Assert
        $response->assertNoContent();

        $this->assertDatabaseMissing('discount_promotions', [
            'id' => $promotion->id,
        ]);
    });

    test('toggleStatus changes promotion active status', function (): void {
        // Arrange
        $promotion = DiscountPromotion::factory()->create(['is_active' => true])->fresh();

        // Act
        $response = $this->put("/api/v1/admin/discount-promotion/{$promotion->id}/status");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['is_active'],
                'message'
            ]);

        expect($response->json('data.is_active'))->toBeFalse();

        $this->assertDatabaseHas('discount_promotions', [
            'id'        => $promotion->id,
            'is_active' => false,
        ]);
    });

    test('statistics returns promotion counts', function (): void {
        // Arrange
        DiscountPromotion::factory()->create(['is_active' => true, 'type' => 'cart_checkout']);
        DiscountPromotion::factory()->create(['is_active' => false, 'type' => 'product_specific']);

        // Act
        $response = $this->get('/api/v1/admin/discount-promotion-statistics');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_promotions',
                    'active_promotions',
                    'inactive_promotions',
                    'product_specific_promotions',
                    'cart_checkout_promotions',
                    'promotions_with_coupons',
                    'promotions_without_coupons',
                ]
            ]);

        expect($response->json('data.total_promotions'))->toBe(2)
            ->and($response->json('data.active_promotions'))->toBe(1)
            ->and($response->json('data.inactive_promotions'))->toBe(1);
    });

})->group('unit', 'controllers', 'discounts');

test('requires authentication', function (): void {

    // Act
    $response = $this->get('/api/v1/admin/discount-promotion');

    // Assert
    $response->assertUnauthorized();
});
