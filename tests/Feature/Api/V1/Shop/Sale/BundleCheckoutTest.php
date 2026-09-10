<?php

declare(strict_types=1);

use App\Actions\Admin\Order\CreateBundlePurchaseAction;
use App\Actions\Admin\Order\CreateOrderAction;
use App\Data\Admin\Order\OrderCreateData;
use App\Data\Admin\Order\OrderItemCreateData;
use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Bundle;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

covers(CreateBundlePurchaseAction::class);

/** @return array{ProductDeliveryOption, ProductDeliveryOption, ProductDeliveryOption} */
function checkoutBundle(): array
{
    $first = ProductDeliveryOption::factory()->create([
        'price'            => 120000, 'capacity' => 2, 'is_featured' => false,
        'fulfillment_type' => FulfillmentTypeEnum::OFFLINE_SERVICE,
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
        'details_json'     => [],
    ]);
    $second = ProductDeliveryOption::factory()->create([
        'price'            => 80000, 'capacity' => 2, 'is_featured' => false,
        'fulfillment_type' => FulfillmentTypeEnum::OFFLINE_SERVICE,
        'delivery_method'  => DeliveryMethodEnum::IN_PERSON,
        'details_json'     => [],
    ]);
    $parent = ProductDeliveryOption::factory()->create([
        'product_id' => Product::factory()->create([
            'productable_type' => ProductableEnum::BUNDLE->value,
            'productable_id'   => Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED])->id,
            'name'             => 'Career Bundle',
        ])->id,
        'name'             => 'Complete package', 'sku' => 'BUNDLE-CHECKOUT',
        'price'            => 100000, 'capacity' => null,
        'fulfillment_type' => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'  => DeliveryMethodEnum::BUNDLE,
        'details_json'     => [],
    ]);
    $parent->bundleComponents()->attach([
        $first->id  => ['allocation' => 100000],
        $second->id => ['allocation' => 0],
    ]);

    return [$parent, $first, $second];
}

it('checks out one Bundle Purchase with allocated components and no parent entitlement', function (): void {
    Queue::fake([ProvisionEnrollmentProviderJob::class]);
    [$parent, $first, $second] = checkoutBundle();
    $this->customer();
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $parent->uuid, 'quantity' => 1,
    ])->assertOk();

    $response = postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
        'payment_data'   => ['transaction_id' => '123456', 'transaction_date' => verta()->formatDate(), 'sender_name' => 'Customer'],
    ]);

    $response->assertCreated()
        // The Bundle is one purchased line; its components are nested, not top-level.
        ->assertJsonCount(1, 'data.order.items')
        ->assertJsonPath('data.order.items.0.type', 'bundle')
        ->assertJsonPath('data.order.grand_total', 100000)
        ->assertJsonPath('data.order.total_item_count', 1)
        ->assertJsonPath('data.order.items.0.base_value', 200000)
        ->assertJsonPath('data.order.items.0.selling_price', 100000)
        ->assertJsonCount(2, 'data.order.items.0.components')
        ->assertJsonPath('data.order.items.0.components.0.product_delivery_option_id', $first->id)
        ->assertJsonPath('data.order.items.0.components.0.name', $first->product->name)
        ->assertJsonPath('data.order.items.0.components.0.sku', $first->sku)
        ->assertJsonPath('data.order.items.0.components.0.price', 120000)
        ->assertJsonPath('data.order.items.0.components.0.paid_amount', 100000)
        ->assertJsonPath('data.order.items.0.components.0.bundle_discount_amount', 20000)
        ->assertJsonPath('data.order.items.0.components.0.status.value', 'completed')
        ->assertJsonPath('data.order.items.0.components.0.enrollment_status.value', 'active')
        ->assertJsonPath('data.order.items.0.components.1.product_delivery_option_id', $second->id)
        ->assertJsonPath('data.order.items.0.components.1.price', 80000)
        ->assertJsonPath('data.order.items.0.components.1.paid_amount', 0)
        ->assertJsonPath('data.order.items.0.components.1.bundle_discount_amount', 80000)
        ->assertJsonPath('data.order.items.0.components.1.status.value', 'completed')
        ->assertJsonPath('data.order.items.0.components.1.enrollment_status.value', 'active');
    assertDatabaseCount('bundle_purchases', 1);
    assertDatabaseCount('order_items', 2);
    assertDatabaseCount('enrollments', 2);
    assertDatabaseMissing('order_items', ['product_delivery_option_id' => $parent->id]);
    assertDatabaseMissing('enrollments', ['product_delivery_option_id' => $parent->id]);
    expect($first->fresh()->reserved_count)->toBe(0);
    expect($second->fresh()->reserved_count)->toBe(0);
});

it('keeps mixed pending and completed order history grouped and immutable', function (bool $pay): void {
    Queue::fake([ProvisionEnrollmentProviderJob::class]);
    [$parent, $first, $second] = checkoutBundle();
    $standalone                = ProductDeliveryOption::factory()->create(['price' => 50000, 'is_featured' => false]);
    $this->customer();
    foreach ([$parent, $standalone] as $option) {
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid, 'quantity' => 1,
        ])->assertOk();
    }
    if ($pay) {
        postJson(route('api.v1.shop.checkout'), ['payment_method' => 'bank_transfer'])->assertCreated();
    } else {
        app(CreateOrderAction::class)->handle(new OrderCreateData(
            status: 'pending', customer_id: $this->user->id,
            items: [new OrderItemCreateData($parent->id, 'full_payment', composition_version: 1), new OrderItemCreateData($standalone->id, 'full_payment')],
        ));
    }
    $order  = Order::query()->sole();
    $url    = '/api/v1/shop/student/orders/'.$order->increment_id;
    $before = getJson($url)->assertOk()
        // One Bundle line and one standalone line, both as purchased lines.
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.grand_total', 150000)
        ->json('data');

    $bundleLine = collect($before['items'])->firstWhere('type', 'bundle');

    expect($bundleLine)->not->toBeNull()
        ->and($bundleLine['status']['value'])->toBe($pay ? 'active' : 'pending_payment')
        ->and($bundleLine['components'][0]['enrollment_status']['value'])->toBe($pay ? 'active' : 'awaiting_payment')
        ->and(collect($before['items'])->where('type', 'product'))->toHaveCount(1);

    $first->updateQuietly(['price' => 400000, 'name' => 'New component name']);
    $first->product->updateQuietly(['name' => 'New Product name']);
    $parent->updateQuietly(['name' => 'New parent name', 'price' => 400000, 'composition_version' => 2, 'bundle_review_required_at' => now()]);
    $parent->bundleComponents()->detach();
    $parent->product->productable->updateQuietly(['full_name' => 'New Bundle name']);

    getJson($url)->assertOk()->assertJsonPath('data', $before);
    getJson('/api/v1/shop/student/orders')->assertOk()
        ->assertJsonPath('data.data.0.items', $before['items']);
})->with(['pending' => false, 'completed' => true]);

it('revalidates a Bundle selection at checkout before writing any purchase records', function (Closure $change): void {
    [$parent, $first, $second] = checkoutBundle();
    $this->customer();
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $parent->uuid, 'quantity' => 1,
    ])->assertOk();
    $change($parent, $first);

    postJson(route('api.v1.shop.checkout'), ['payment_method' => 'bank_transfer'])->assertUnprocessable();

    assertDatabaseCount('orders', 0);
    assertDatabaseCount('bundle_purchases', 0);
    assertDatabaseCount('order_items', 0);
    assertDatabaseCount('cart_items', 1);
    expect($first->fresh()->reserved_count)->toBe(0);
    expect($second->fresh()->reserved_count)->toBe(0);
})->with([
    'stale composition'             => [fn ($parent, $first) => $parent->updateQuietly(['composition_version' => 2])],
    'review required'               => [fn ($parent, $first) => $parent->updateQuietly(['bundle_review_required_at' => now()])],
    'component capacity'            => [fn ($parent, $first) => $first->updateQuietly(['capacity' => 0])],
    'component publication'         => [fn ($parent, $first) => $first->updateQuietly(['status' => 'archived'])],
    'component Product publication' => [fn ($parent, $first) => $first->product->updateQuietly(['status' => 'archived'])],
    'registration window'           => [fn ($parent, $first) => $first->updateQuietly(['registration_start_date' => now()->addDay()])],
    'availability window'           => [fn ($parent, $first) => $first->updateQuietly(['available_to' => now()->subDay()])],
    'invalid allocation'            => [fn ($parent, $first) => $first->updateQuietly(['price' => 90000])],
    'prepayment cart corruption'    => [fn ($parent, $first) => DB::table('cart_items')->update(['payment_type' => 'pre_payment'])],
]);

it('rolls back every reservation and purchase when a component enrollment cannot be persisted', function (): void {
    [$parent, $first, $second] = checkoutBundle();
    $this->customer();
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $parent->uuid, 'quantity' => 1,
    ])->assertOk();
    DB::statement('ALTER TABLE enrollments ADD CONSTRAINT reject_bundle_component CHECK (product_delivery_option_id <> '.(int) $second->id.')');
    try {
        postJson(route('api.v1.shop.checkout'), ['payment_method' => 'bank_transfer'])->assertServerError();
    } finally {
        DB::statement('ALTER TABLE enrollments DROP CONSTRAINT reject_bundle_component');
    }

    assertDatabaseCount('orders', 0);
    assertDatabaseCount('bundle_purchases', 0);
    assertDatabaseCount('order_items', 0);
    assertDatabaseCount('enrollments', 0);
    assertDatabaseCount('cart_items', 1);
    expect($first->fresh()->reserved_count)->toBe(0);
    expect($second->fresh()->reserved_count)->toBe(0);
});

it('cancels the original reserved components even after the Bundle is recomposed', function (): void {
    [$parent, $first, $second] = checkoutBundle();
    $this->customer();
    $order = app(CreateOrderAction::class)->handle(new OrderCreateData(
        status: 'pending', customer_id: $this->user->id,
        items: [new OrderItemCreateData($parent->id, 'full_payment', composition_version: 1)],
    ));
    $parent->bundleComponents()->detach();

    postJson('/api/v1/shop/student/orders/'.$order->increment_id.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.items.0.type', 'bundle')
        ->assertJsonPath('data.items.0.status.value', 'cancelled');

    expect($first->fresh()->reserved_count)->toBe(0);
    expect($second->fresh()->reserved_count)->toBe(0);
});

it('rejects a changed allocation total without reserving seats or writing purchases', function (): void {
    [$parent, $first, $second] = checkoutBundle();
    $this->customer();
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $parent->uuid, 'quantity' => 1,
    ])->assertOk();
    $parent->bundleComponents()->updateExistingPivot($first->id, ['allocation' => 90000]);

    postJson(route('api.v1.shop.checkout'), ['payment_method' => 'bank_transfer'])
        ->assertUnprocessable();

    assertDatabaseCount('orders', 0);
    assertDatabaseCount('bundle_purchases', 0);
    assertDatabaseCount('order_items', 0);
    assertDatabaseCount('enrollments', 0);
    assertDatabaseCount('cart_items', 1);
    expect($first->fresh()->reserved_count)->toBe(0);
    expect($second->fresh()->reserved_count)->toBe(0);
});
