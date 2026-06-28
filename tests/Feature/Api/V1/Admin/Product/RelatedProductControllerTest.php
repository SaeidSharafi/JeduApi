<?php

declare(strict_types=1);

use App\Enums\Product\RelationTypeEnum;
use App\Models\Product;
use Illuminate\Testing\Fluent\AssertableJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

describe('User with permissions - Related Products Management', function (): void {
    beforeEach(function (): void {
        $this->mainProduct       = Product::factory()->create();
        $this->relatedProducts   = Product::factory()->count(3)->create();
        $this->crossSellProducts = Product::factory()->count(2)->create();
        $this->upsellProducts    = Product::factory()->count(2)->create();
    });

    it('should return an empty list when no related products exist', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_VIEW,
        ]);

        $response = $this->getJson(route('api.v1.admin.products.related-products.index', [
            'product' => $this->mainProduct->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('should return all related products for a product', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_VIEW,
        ]);

        // Attach related products
        foreach ($this->relatedProducts as $related) {
            $this->mainProduct->relatedProducts()->attach($related->id, [
                'relation_type' => RelationTypeEnum::RELATED->value,
            ]);
        }

        foreach ($this->crossSellProducts as $crossSell) {
            $this->mainProduct->relatedProducts()->attach($crossSell->id, [
                'relation_type' => RelationTypeEnum::CROSS_SELL->value,
            ]);
        }

        $response = $this->getJson(route('api.v1.admin.products.related-products.index', [
            'product' => $this->mainProduct->id,
        ]));

        $response->assertOk()
            ->assertJsonCount(5, 'data'); // 3 related + 2 cross-sell

        $response->assertJson(
            fn (AssertableJson $json) => $json->has('data.0', fn (AssertableJson $json) => $json->has('product_id')
                ->has('related_product_id')
                ->has('relation_type')
                ->has('relation_type.value')
                ->has('relation_type.label')
                ->has('related_product')
                ->has('created_at')
                ->etc()
            )
                ->etc()
        );
    });

    it('should filter related products by relation type', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_VIEW,
        ]);

        // Attach products with different relation types
        $this->mainProduct->relatedProducts()->attach($this->relatedProducts->pluck('id'), [
            'relation_type' => RelationTypeEnum::RELATED->value,
        ]);

        $this->mainProduct->relatedProducts()->attach($this->crossSellProducts->pluck('id'), [
            'relation_type' => RelationTypeEnum::CROSS_SELL->value,
        ]);

        // Filter by RELATED type
        $response = $this->getJson(route('api.v1.admin.products.related-products.index', [
            'product'       => $this->mainProduct->id,
            'relation_type' => RelationTypeEnum::RELATED->value,
        ]));

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $item) {
            expect($item['relation_type']['value'])->toBe(RelationTypeEnum::RELATED->value);
        }

        // Filter by CROSS_SELL type
        $response = $this->getJson(route('api.v1.admin.products.related-products.index', [
            'product'       => $this->mainProduct->id,
            'relation_type' => RelationTypeEnum::CROSS_SELL->value,
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $item) {
            expect($item['relation_type']['value'])->toBe(RelationTypeEnum::CROSS_SELL->value);
        }
    });

    it('should sync related products for a product', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        $data = [
            'product_ids'   => $this->relatedProducts->pluck('id')->toArray(),
            'relation_type' => RelationTypeEnum::RELATED->value,
        ];

        $response = $this->postJson(
            route('api.v1.admin.products.related-products.store', ['product' => $this->mainProduct->id]),
            $data
        );

        $response->assertCreated()
            ->assertJsonCount(3, 'data');

        // Verify database
        foreach ($this->relatedProducts as $related) {
            $this->assertDatabaseHas('related_products', [
                'product_id'         => $this->mainProduct->id,
                'related_product_id' => $related->id,
                'relation_type'      => RelationTypeEnum::RELATED->value,
            ]);
        }
    });

    it('should replace existing relations of the same type when syncing', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        // Initial sync
        $this->mainProduct->relatedProducts()->attach($this->relatedProducts->pluck('id'), [
            'relation_type' => RelationTypeEnum::RELATED->value,
        ]);

        // Sync with different products
        $newProducts = Product::factory()->count(2)->create();
        $data        = [
            'product_ids'   => $newProducts->pluck('id')->toArray(),
            'relation_type' => RelationTypeEnum::RELATED->value,
        ];

        $response = $this->postJson(
            route('api.v1.admin.products.related-products.store', ['product' => $this->mainProduct->id]),
            $data
        );

        $response->assertCreated()
            ->assertJsonCount(2, 'data');

        // Verify old relations are removed
        foreach ($this->relatedProducts as $related) {
            $this->assertDatabaseMissing('related_products', [
                'product_id'         => $this->mainProduct->id,
                'related_product_id' => $related->id,
                'relation_type'      => RelationTypeEnum::RELATED->value,
            ]);
        }

        // Verify new relations exist
        foreach ($newProducts as $product) {
            $this->assertDatabaseHas('related_products', [
                'product_id'         => $this->mainProduct->id,
                'related_product_id' => $product->id,
                'relation_type'      => RelationTypeEnum::RELATED->value,
            ]);
        }
    });

    it('should not affect relations of different types when syncing', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        // Add cross-sell products
        $this->mainProduct->relatedProducts()->attach($this->crossSellProducts->pluck('id'), [
            'relation_type' => RelationTypeEnum::CROSS_SELL->value,
        ]);

        // Sync RELATED type (should not affect CROSS_SELL)
        $data = [
            'product_ids'   => $this->relatedProducts->pluck('id')->toArray(),
            'relation_type' => RelationTypeEnum::RELATED->value,
        ];

        $this->postJson(
            route('api.v1.admin.products.related-products.store', ['product' => $this->mainProduct->id]),
            $data
        );

        // Verify cross-sell products still exist
        foreach ($this->crossSellProducts as $crossSell) {
            $this->assertDatabaseHas('related_products', [
                'product_id'         => $this->mainProduct->id,
                'related_product_id' => $crossSell->id,
                'relation_type'      => RelationTypeEnum::CROSS_SELL->value,
            ]);
        }
    });

    it('should prevent a product from being related to itself', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        $data = [
            'product_ids'   => [$this->mainProduct->id, $this->relatedProducts[0]->id],
            'relation_type' => RelationTypeEnum::RELATED->value,
        ];

        $response = $this->postJson(
            route('api.v1.admin.products.related-products.store', ['product' => $this->mainProduct->id]),
            $data
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['product_ids']);

        $this->assertDatabaseMissing('related_products', [
            'product_id'         => $this->mainProduct->id,
            'related_product_id' => $this->mainProduct->id,
        ]);
    });

    it('should validate product_ids exist in database', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        $data = [
            'product_ids'   => [99999, 88888], // Non-existent IDs
            'relation_type' => RelationTypeEnum::RELATED->value,
        ];

        $response = $this->postJson(
            route('api.v1.admin.products.related-products.store', ['product' => $this->mainProduct->id]),
            $data
        );
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['product_ids.0', 'product_ids.1']);
    });

    it('should validate relation_type is valid', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        $data = [
            'product_ids'   => $this->relatedProducts->pluck('id')->toArray(),
            'relation_type' => 'invalid_type',
        ];

        $response = $this->postJson(
            route('api.v1.admin.products.related-products.store', ['product' => $this->mainProduct->id]),
            $data
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['relation_type']);
    });

    it('should remove a specific related product relationship', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        // Attach a related product
        $relatedProduct = $this->relatedProducts->first();
        $this->mainProduct->relatedProducts()->attach($relatedProduct->id, [
            'relation_type' => RelationTypeEnum::RELATED->value,
        ]);

        $this->assertDatabaseHas('related_products', [
            'product_id'         => $this->mainProduct->id,
            'related_product_id' => $relatedProduct->id,
            'relation_type'      => RelationTypeEnum::RELATED->value,
        ]);

        $response = $this->deleteJson(
            route('api.v1.admin.products.related-products.destroy', [
                'product'        => $this->mainProduct->id,
                'relatedProduct' => $relatedProduct->id,
                'relation_type'  => RelationTypeEnum::RELATED->value,
            ])
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('related_products', [
            'product_id'         => $this->mainProduct->id,
            'related_product_id' => $relatedProduct->id,
            'relation_type'      => RelationTypeEnum::RELATED->value,
        ]);
    });

    it('should only remove the specified relation type when deleting', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        $relatedProduct = $this->relatedProducts->first();

        // Attach same product with different relation types
        $this->mainProduct->relatedProducts()->attach($relatedProduct->id, [
            'relation_type' => RelationTypeEnum::RELATED->value,
        ]);
        $this->mainProduct->relatedProducts()->attach($relatedProduct->id, [
            'relation_type' => RelationTypeEnum::CROSS_SELL->value,
        ]);

        // Delete only RELATED type
        $response = $this->deleteJson(
            route('api.v1.admin.products.related-products.destroy', [
                'product'        => $this->mainProduct->id,
                'relatedProduct' => $relatedProduct->id,
                'relation_type'  => RelationTypeEnum::RELATED->value,
            ])
        );

        $response->assertNoContent();

        // RELATED should be gone
        $this->assertDatabaseMissing('related_products', [
            'product_id'         => $this->mainProduct->id,
            'related_product_id' => $relatedProduct->id,
            'relation_type'      => RelationTypeEnum::RELATED->value,
        ]);

        // CROSS_SELL should still exist
        $this->assertDatabaseHas('related_products', [
            'product_id'         => $this->mainProduct->id,
            'related_product_id' => $relatedProduct->id,
            'relation_type'      => RelationTypeEnum::CROSS_SELL->value,
        ]);
    });

    it('should require relation_type parameter when deleting', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        $relatedProduct = $this->relatedProducts->first();

        $response = $this->deleteJson(
            route('api.v1.admin.products.related-products.destroy', [
                'product'        => $this->mainProduct->id,
                'relatedProduct' => $relatedProduct->id,
            ])
        );

        $response->assertStatus(422)
            ->assertJson([
                'message' => __('validation.custom.product.related_product_type_invalid'),
            ]);
    });

    it('should validate relation_type parameter when deleting', function (): void {
        $this->authorized_user([
            App\Enums\PermissionEnum::PRODUCT_UPDATE,
        ]);

        $relatedProduct = $this->relatedProducts->first();

        $response = $this->deleteJson(
            route('api.v1.admin.products.related-products.destroy', [
                'product'        => $this->mainProduct->id,
                'relatedProduct' => $relatedProduct->id,
                'relation_type'  => 'invalid_type',
            ])
        );

        $response->assertStatus(422)
            ->assertJson([
                'message' => __('validation.custom.product.related_product_type_invalid'),
            ]);
    });
});

describe('User without permissions - Related Products Management', function (): void {
    beforeEach(function (): void {
        $this->mainProduct     = Product::factory()->create();
        $this->relatedProducts = Product::factory()->count(2)->create();
    });

    it('should deny access to list related products without permission', function (): void {
        $this->authorized_user([]);

        $response = $this->getJson(route('api.v1.admin.products.related-products.index', [
            'product' => $this->mainProduct->id,
        ]));

        $response->assertForbidden();
    });

    it('should deny access to sync related products without permission', function (): void {
        $this->authorized_user([]);

        $data = [
            'product_ids'   => $this->relatedProducts->pluck('id')->toArray(),
            'relation_type' => RelationTypeEnum::RELATED->value,
        ];

        $response = $this->postJson(
            route('api.v1.admin.products.related-products.store', ['product' => $this->mainProduct->id]),
            $data
        );

        $response->assertForbidden();
    });

    it('should deny access to delete related product without permission', function (): void {
        $this->authorized_user([]);

        $response = $this->deleteJson(
            route('api.v1.admin.products.related-products.destroy', [
                'product'        => $this->mainProduct->id,
                'relatedProduct' => $this->relatedProducts->first()->id,
                'relation_type'  => RelationTypeEnum::RELATED->value,
            ])
        );

        $response->assertForbidden();
    });
});
