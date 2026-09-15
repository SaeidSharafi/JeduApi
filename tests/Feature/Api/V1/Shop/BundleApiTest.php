<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;
use App\Models\Product;
use App\Models\ProductDeliveryOption;

covers(App\Http\Controllers\Api\Shop\Product\BundleController::class, App\Query\ProductQueryService::class);

function makeBundleStorefrontFixture(array $parentOverrides = []): array
{
    $bundle        = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $parentProduct = Product::factory()->create([
        'productable_type' => ProductableEnum::BUNDLE->value,
        'productable_id'   => $bundle->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
        ...$parentOverrides,
    ]);
    $componentProduct = Product::factory()->withCourse()->create();
    $component        = ProductDeliveryOption::factory()->create([
        'product_id'       => $componentProduct->id,
        'price'            => 100000,
        'delivery_method'  => DeliveryMethodEnum::LMS_MOODLE,
        'fulfillment_type' => FulfillmentTypeEnum::ONLINE_SERVICE,
        'details_json'     => ['moodle_course_id' => 42, 'schedule_days' => ['saturday']],
    ]);
    $parentOption = ProductDeliveryOption::factory()->create([
        'product_id'       => $parentProduct->id,
        'price'            => 75000,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'details_json'     => [],
    ]);
    $parentOption->bundleComponents()->attach($component->id, ['allocation' => 75000]);

    return [$bundle, $parentProduct, $parentOption, $component];
}

it('lists only currently saleable bundles with pagination', function (): void {
    [$bundle] = makeBundleStorefrontFixture();
    makeBundleStorefrontFixture(['is_visible' => false]);

    $response = $this->getJson(route('api.v1.shop.bundles.index', ['per_page' => 1]));

    $response->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.product_type.value', ProductableEnum::BUNDLE->value);
});

it('returns bundle detail with component identity and server pricing', function (): void {
    [, $product] = makeBundleStorefrontFixture();

    $response = $this->getJson(route('api.v1.shop.bundles.show', ['product' => $product->slug]));

    $response->assertOk()
        ->assertJsonPath('data.productable_type', ProductableEnum::BUNDLE->value)
        ->assertJsonPath('data.options.0.delivery_method', DeliveryMethodEnum::BUNDLE->value)
        ->assertJsonPath('data.options.0.fulfillment_type', FulfillmentTypeEnum::COMPOSITE->value)
        ->assertJsonPath('data.options.0.base_value', 100000)
        ->assertJsonPath('data.options.0.selling_price', 75000)
        ->assertJsonPath('data.options.0.discount_amount', 25000)
        ->assertJsonPath('data.options.0.components.0.allocation', 75000)
        ->assertJsonPath('data.options.0.components.0.provider_presentation', 'Moodle')
        ->assertJsonPath('data.options.0.components.0.schedule_days.0', 'saturday')
        ->assertJsonMissingPath('data.options.0.components.0.details_json')
        ->assertJsonMissingPath('data.options.0.components.0.provider_id');
});

it('preserves zero allocations and multiple composite options', function (): void {
    [, $product, , $component] = makeBundleStorefrontFixture();
    $second                    = ProductDeliveryOption::factory()->create([
        'product_id'       => $product->id,
        'price'            => 0,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'details_json'     => [],
    ]);
    $second->bundleComponents()->attach($component->id, ['allocation' => 0]);

    $this->getJson(route('api.v1.shop.bundles.show', ['product' => $product->slug]))
        ->assertOk()
        ->assertJsonCount(2, 'data.options')
        ->assertJsonFragment(['allocation' => 0]);
});

it('hides unpublished bundles from both public endpoints', function (): void {
    [, $product] = makeBundleStorefrontFixture();
    $product->update(['status' => PublicationStatusEnum::DRAFT]);

    $this->getJson(route('api.v1.shop.bundles.index'))->assertJsonMissing(['slug' => $product->slug]);
    $this->getJson(route('api.v1.shop.bundles.show', ['product' => $product->slug]))->assertNotFound();
});

it('rejects pagination values above the public maximum', function (): void {
    $this->getJson(route('api.v1.shop.bundles.index', ['per_page' => 101]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});

it('excludes review-required bundles from the public catalog', function (): void {
    [, $product, $option] = makeBundleStorefrontFixture();
    $option->update(['bundle_review_required_at' => now()]);

    $this->getJson(route('api.v1.shop.bundles.index'))->assertJsonMissing(['slug' => $product->slug]);
    $this->getJson(route('api.v1.shop.bundles.show', ['product' => $product->slug]))->assertNotFound();
});

it('does not return bundles from global search', function (): void {
    [, $product] = makeBundleStorefrontFixture();

    $this->getJson(route('api.v1.shop.search', ['q' => $product->name]))
        ->assertOk()
        ->assertJsonMissing(['slug' => $product->slug]);
});

it('excludes bundles whose component is outside its availability window', function (): void {
    [, $product, , $component] = makeBundleStorefrontFixture();
    $component->update(['available_to' => now()->subDay()]);

    $this->getJson(route('api.v1.shop.bundles.index'))->assertJsonMissing(['slug' => $product->slug]);
    $this->getJson(route('api.v1.shop.bundles.show', ['product' => $product->slug]))->assertNotFound();
});

it('rejects a non-Bundle Product slug on the Bundle detail route', function (): void {
    $product = Product::factory()->withCourse()->withDeliveryOptions(1)->create();

    $this->getJson(route('api.v1.shop.bundles.show', ['product' => $product->slug]))->assertNotFound();
});
