<?php

use App\Models\Product;

uses(\Tests\AuthTestTrait::class);
describe('list filters', function () {
    it('should filter by name', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_VIEW_ANY]);
        \App\Models\Product::factory()->count(10)->create();
        \App\Models\Product::factory()->create([
            'name'        => 'Test Product',
            'short_name'  => 'Test Short Name',
            'is_visible'  => true,
            'is_featured' => false,
            'status'      => \App\Enums\PublicationStatusEnum::PUBLISHED,
        ]);
        $response = $this->getJson(route('api.v1.admin.product.index', [
            'filter'  => ['name' => 'Test Product'],
            'perPage' => 10,
        ]));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['name' => 'Test Product']);
    });

    it('should filter by short_name', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_VIEW_ANY]);
        \App\Models\Product::factory()->count(10)->create();
        \App\Models\Product::factory()->create([
            'name'        => 'Another Product',
            'short_name'  => 'Test Short Name',
            'is_visible'  => true,
            'is_featured' => false,
            'status'      => \App\Enums\PublicationStatusEnum::PUBLISHED,
        ]);
        $response = $this->getJson(route('api.v1.admin.product.index', [
            'filter'  => ['short_name' => 'Test Short Name'],
            'perPage' => 10,
        ]));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['short_name' => 'Test Short Name']);
    });

    it('should filter by is_visible', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_VIEW_ANY]);
        \App\Models\Product::factory()->count(10)->create(
            [
                'is_visible' => false,
            ]
        );
        \App\Models\Product::factory()->create([
            'name'        => 'Visible Product',
            'short_name'  => 'Visible Short Name',
            'is_visible'  => true,
            'is_featured' => false,
            'status'      => \App\Enums\PublicationStatusEnum::PUBLISHED,
        ]);
        $response = $this->getJson(route('api.v1.admin.product.index', [
            'filter'  => ['is_visible' => true],
            'perPage' => 10,
        ]));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['is_visible' => true]);
    });

    it('should filter by is_featured', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_VIEW_ANY]);
        \App\Models\Product::factory()->count(10)->create(
            [
                'is_featured' => false,
            ]
        );
        \App\Models\Product::factory()->create([
            'name'        => 'Featured Product',
            'short_name'  => 'Featured Short Name',
            'is_visible'  => true,
            'is_featured' => true,
            'status'      => \App\Enums\PublicationStatusEnum::PUBLISHED,
        ]);
        $response = $this->getJson(route('api.v1.admin.product.index', [
            'filter'  => ['is_featured' => true],
            'perPage' => 10,
        ]));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment(['is_featured' => true]);
    });

    it('should filter by status', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_VIEW_ANY]);
        \App\Models\Product::factory()->count(10)->create([
            'status' => \App\Enums\PublicationStatusEnum::DRAFT
        ]);
        \App\Models\Product::factory()->create([
            'name'        => 'Published Product',
            'short_name'  => 'Published Short Name',
            'is_visible'  => true,
            'is_featured' => false,
            'status'      => \App\Enums\PublicationStatusEnum::PUBLISHED,
        ]);
        $response = $this->getJson(route('api.v1.admin.product.index', [
            'filter'  => ['status' => \App\Enums\PublicationStatusEnum::PUBLISHED->value],
            'perPage' => 10,
        ]));
        $response->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonFragment([
                'status' => [
                    'value' => \App\Enums\PublicationStatusEnum::PUBLISHED->value,
                    'label' => \App\Enums\PublicationStatusEnum::PUBLISHED->translate(),
                ]
            ]);
    });
});

describe('Controller Tests', function () {
    beforeEach(function () {
        $this->category = \App\Models\Category::factory()->create();
    });
    it('should return a list of products', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_VIEW_ANY]);
        \App\Models\Product::factory()->count(10)->create(
            [
                'status' => \App\Enums\PublicationStatusEnum::PUBLISHED,
            ]
        );
        \App\Models\Product::factory()->count(10)->create(
            [
                'status' => \App\Enums\PublicationStatusEnum::DRAFT,
            ]
        );
        $response = $this->getJson(route('api.v1.admin.product.index'));
        $response->assertOk()
            ->assertJsonCount(10, 'data.data');
    });

    it('should create a product', function () {
        $data = Product::factory()->make();
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_CREATE]);
        $product = \App\Models\Product::factory()->make();
        $data = [
            ...$product->toArray(),
            'name'         => 'New Product',
            'force_create' => true,
            'categories'   => [$this->category->id],
        ];
        $response = $this->postJson(route('api.v1.admin.product.store'), $data);
        $response->assertCreated()
            ->assertJsonFragment(['name' => 'New Product']);
    });

    it('should show a product', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_VIEW]);
        $product = \App\Models\Product::factory()->create()->fresh();
        $response = $this->getJson(route('api.v1.admin.product.show', ['product' => $product->id]));
        $response->assertOk()
            ->assertJsonFragment(['name' => $product->name]);
    });

    it('should update a product', function () {
        $product = \App\Models\Product::factory()->create();
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_UPDATE]);
        $data = [
            ...$product->toArray(),
            'name'        => 'Updated Product',
            'short_name'  => 'Updated Short Name',
            'is_visible'  => true,
            'is_featured' => false,
            'status'      => \App\Enums\PublicationStatusEnum::PUBLISHED,
            'categories'  => [$this->category->id],
        ];
        $response = $this->putJson(route('api.v1.admin.product.update', ['product' => $product->id]), $data);
        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Product']);
    });

    it('should delete a product', function () {
        $product = \App\Models\Product::factory()->create()->fresh();
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_DELETE]);
        $response = $this->deleteJson(route('api.v1.admin.product.destroy', ['product' => $product->id]));
        $response->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseCount('products', 0);
    });
    it('should not delete a product if unauthorized', function () {
        $this->unauthorized_user();
        $product = \App\Models\Product::factory()->create();
        $response = $this->deleteJson(route('api.v1.admin.product.destroy', ['product' => $product->id]));
        $response->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    });
    it('should not create a product if unauthorized', function () {
        $this->unauthorized_user();
        $product = \App\Models\Product::factory()->make();
        $data = [
            ...$product->toArray(),
            'force_create' => true,
            'categories'   => [$this->category->id],
        ];
        $response = $this->postJson(route('api.v1.admin.product.store'), $data);
        $response->assertForbidden();
    });
    it('should not update a product if unauthorized', function () {
        $this->unauthorized_user();
        $product = \App\Models\Product::factory()->create()->fresh();
        $data = [
            ...$product->toArray(),
            'name'        => 'Unauthorized Update',
            'short_name'  => 'Unauthorized Short Name',
            'is_visible'  => true,
            'is_featured' => false,
            'status'      => \App\Enums\PublicationStatusEnum::PUBLISHED,
            'categories'  => [$this->category->id],
        ];
        $response = $this->putJson(route('api.v1.admin.product.update', ['product' => $product->id]), $data);
        $response->assertForbidden();
    });
    it('should not show a product if unauthorized', function () {
        $this->unauthorized_user();
        $product = \App\Models\Product::factory()->create()->fresh();
        $response = $this->getJson(route('api.v1.admin.product.show', ['product' => $product->id]));
        $response->assertForbidden();
    });
    it('should not list products if unauthorized', function () {
        $this->unauthorized_user();
        $response = $this->getJson(route('api.v1.admin.product.index'));
        $response->assertForbidden();
    });
    it('should not delete a product if it does not exist', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_DELETE]);
        $response = $this->deleteJson(route('api.v1.admin.product.destroy', ['product' => 999]));
        $response->assertNotFound();
    });
    it('should not update a product if it does not exist', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_UPDATE]);
        $data = \App\Models\Product::factory()->make();
        $response = $this->putJson(route('api.v1.admin.product.update', ['product' => 999]), $data->toArray());
        $response->assertNotFound();
    });
    it('should not create a product with invalid data', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_CREATE]);
        $product = \App\Models\Product::factory()->make();
        $data = [
            ...$product->toArray(),
            'force_create' => true,
            'name'         => '', // Invalid name
            'short_name'   => 'Invalid Short Name',
            'is_visible'   => true,
            'is_featured'  => false,
            'status'       => \App\Enums\PublicationStatusEnum::PUBLISHED,
            'categories'   => [$this->category->id],
        ];
        $response = $this->postJson(route('api.v1.admin.product.store'), $data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
    it('should not update a product with invalid data', function () {
        $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_UPDATE]);
        $product = \App\Models\Product::factory()->create();
        $data = [
            ...($product->toArray()),
            'name'        => '', // Invalid name
            'short_name'  => 'Invalid Short Name',
            'is_visible'  => true,
            'is_featured' => false,
            'status'      => \App\Enums\PublicationStatusEnum::PUBLISHED,
            'categories'  => [$this->category->id],
        ];
        $response = $this->putJson(route('api.v1.admin.product.update', ['product' => $product->id]), $data);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
});

describe('Product Creation tests', function () {
    beforeEach(function () {
        $this->category = \App\Models\Category::factory()->create();
    });
    it('should not create a published  product when another published product with same productable exist',
        function () {
            $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_CREATE]);
            $course = \App\Models\Course::factory()->create()->fresh();
            $existingProduct = \App\Models\Product::factory()->create([
                'status'           => \App\Enums\PublicationStatusEnum::PUBLISHED,
                'productable_type' => \App\Enums\ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
            ]);
            $data = \App\Models\Product::factory()->make([
                'productable_type' => $existingProduct->productable_type,
                'productable_id'   => $existingProduct->productable_id,
            ])->toArray();
            $data['status'] = \App\Enums\PublicationStatusEnum::PUBLISHED->value;
            $data['force_create'] = false;
            $data['categories'] = [$this->category->id];
            $response = $this->postJson(route('api.v1.admin.product.store'), $data);
            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['productable_id']);

        });
    it('should create a product with force_create when another published product with same productable exist',
        function () {
            $this->authorized_user([\App\Enums\PermissionEnum::PRODUCT_CREATE]);
            $course = \App\Models\Course::factory()->create()->fresh();
            $existingProduct = \App\Models\Product::factory()->create([
                'status'           => \App\Enums\PublicationStatusEnum::PUBLISHED,
                'productable_type' => \App\Enums\ProductableEnum::COURSE->value,
                'productable_id'   => $course->id,
            ]);
            $data = \App\Models\Product::factory()->make([
                'productable_type' => $existingProduct->productable_type,
                'productable_id'   => $existingProduct->productable_id,
            ])->toArray();
            $data['status'] = \App\Enums\PublicationStatusEnum::PUBLISHED->value;
            $data['force_create'] = true;
            $data['categories'] = [$this->category->id];
            $response = $this->postJson(route('api.v1.admin.product.store'), $data);
            $response->assertCreated()
                ->assertJsonFragment(['name' => $data['name']]);
            \Pest\Laravel\assertDatabaseHas('products', [
                'id'     => $existingProduct->id,
                'status' => \App\Enums\PublicationStatusEnum::ARCHIVED->value,
            ]);
            \Pest\Laravel\assertDatabaseHas('products', [
                'productable_type' => $existingProduct->productable_type,
                'productable_id'   => $existingProduct->productable_id,
                'status'           => \App\Enums\PublicationStatusEnum::PUBLISHED->value,
            ]);
        });
});
