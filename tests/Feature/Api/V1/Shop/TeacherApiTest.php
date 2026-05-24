<?php

declare(strict_types=1);

use App\Models\Teacher;

describe('Shop TeacherController', function (): void {
    it('returns teacher details by uuid', function (): void {
        // Arrange
        $teacher = Teacher::factory()->create()->fresh();

        // Act
        $response = $this->getJson("/api/v1/shop/teachers/{$teacher->uuid}");

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'first_name',
                'last_name',
                'bio',
                'avatar_url',
                'rate',
                'gender',
                'social_links',
            ],
        ]);
        $response->assertJsonPath('data.uuid', $teacher->uuid);
        $response->assertJsonPath('data.first_name', $teacher->first_name);
        $response->assertJsonPath('data.last_name', $teacher->last_name);
    });

    it('returns 404 for non-existent teacher uuid', function (): void {
        // Arrange
        $nonExistentUuid = '00000000-0000-0000-0000-000000000000';

        // Act
        $response = $this->getJson("/api/v1/shop/teachers/{$nonExistentUuid}");

        // Assert
        $response->assertNotFound();
    });
});
describe('Shop ProductTeacherController', function (): void {
    it('returns teachers associated with a product', function (): void {
        $teacher1 = Teacher::factory()->create();
        $teacher2 = Teacher::factory()->create();

        $product = App\Models\Product::factory()
            ->withCourse()
            ->create();

        $pdo = App\Models\ProductDeliveryOption::factory()
            ->for($product)
            ->create();
        $pdo->teachers()->attach([$teacher1->id, $teacher2->id]);

        $response = $this->getJson(route('api.v1.shop.product.teachers', ['product' => $product->slug]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['uuid' => $teacher1->uuid]);
        $response->assertJsonFragment(['uuid' => $teacher2->uuid]);
    });
});
