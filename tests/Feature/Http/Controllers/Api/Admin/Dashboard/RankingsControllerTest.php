<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\Payment\PaymentPurposeEnum;
use App\Enums\Payment\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Bundle;
use App\Models\BundlePurchase;
use App\Models\Category;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Vendor;

use function Pest\Laravel\getJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

const RANKINGS_ENDPOINT = '/api/v1/admin/dashboard/rankings';

/**
 * @return array{Product, ProductDeliveryOption}
 */
function rankingsProduct(Vendor $vendor, string $name, int $price): array
{
    $product = Product::factory()->create([
        'vendor_id' => $vendor->id,
        'name'      => $name,
    ]);

    $option = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => $price,
    ]);

    return [$product, $option];
}

function rankingsOrder(int $grandTotal): Order
{
    return Order::factory()->create([
        'status'      => OrderStatusEnum::COMPLETED,
        'grand_total' => $grandTotal,
    ]);
}

function rankingsPay(Order $order, int $amount, PaymentMethodEnum $method = PaymentMethodEnum::BANK_TRANSFER): Payment
{
    return Payment::factory()->create([
        'order_id' => $order->id,
        'amount'   => $amount,
        'method'   => $method,
        'purpose'  => PaymentPurposeEnum::ORDER,
        'status'   => PaymentStatusEnum::COMPLETED,
    ]);
}

function rankingsLine(
    Order $order,
    ProductDeliveryOption $option,
    Vendor $vendor,
    int $total,
    int $refunded = 0,
    ?BundlePurchase $purchase = null,
): OrderItem {
    return OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'bundle_purchase_id'         => $purchase?->id,
        'product_delivery_option_id' => $option->id,
        'vendor_id'                  => $vendor->id,
        'name'                       => $option->name,
        'price'                      => $total,
        'total'                      => $total,
        'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
        'status'                     => OrderItemStatusEnum::COMPLETED,
        'total_refunded'             => $refunded,
    ]);
}

/**
 * @return array{purchase: BundlePurchase, vendor: Vendor, component_vendors: list<Vendor>}
 */
function rankingsBundlePurchase(Order $order, int $sellingPrice, int $componentRefunded = 0): array
{
    $bundleVendor  = Vendor::factory()->create(['name' => 'Bundle Vendor']);
    $bundle        = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $bundleProduct = Product::factory()->create([
        'vendor_id'        => $bundleVendor->id,
        'name'             => 'Career Bundle',
        'productable_type' => ProductableEnum::BUNDLE->value,
        'productable_id'   => $bundle->id,
    ]);
    $parent = ProductDeliveryOption::factory()->create([
        'product_id' => $bundleProduct->id,
        'price'      => $sellingPrice,
    ]);

    $purchase = BundlePurchase::create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $parent->id,
        'bundle_name'                => $bundle->full_name,
        'product_name'               => $bundleProduct->name,
        'name'                       => $parent->name,
        'sku'                        => $parent->sku,
        'base_value'                 => 100_000,
        'selling_price'              => $sellingPrice,
        'composition_version'        => 1,
        'checkout_status'            => OrderStatusEnum::COMPLETED,
        'product_data_snapshot_json' => [],
    ]);

    $componentVendors = [];

    foreach ([['Component Alpha', 60_000], ['Component Beta', 40_000]] as [$name, $allocation]) {
        $componentVendor     = Vendor::factory()->create(['name' => $name.' Vendor']);
        [, $componentOption] = rankingsProduct($componentVendor, $name, $allocation);

        rankingsLine($order, $componentOption, $componentVendor, $allocation, $componentRefunded, $purchase);

        $componentVendors[] = $componentVendor;
    }

    return ['purchase' => $purchase, 'vendor' => $bundleVendor, 'component_vendors' => $componentVendors];
}

it('rejects an unauthenticated request', function (): void {
    getJson(RANKINGS_ENDPOINT)->assertUnauthorized();
});

it('forbids a staff member holding only the dashboard view permission', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_VIEW->value]);

    getJson(RANKINGS_ENDPOINT)->assertForbidden();
});

it('returns empty ranking arrays when the period has no orders', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'data'     => ['top_products', 'categories', 'vendors'],
        'metadata' => [
            'generated_at',
            'cached',
            'cache_ttl',
            'timezone',
            'currency',
        ],
    ]);
    $response->assertJsonPath('data.top_products', []);
    $response->assertJsonPath('data.categories', []);
    $response->assertJsonPath('data.vendors', []);
    $response->assertJsonPath('metadata.cached', false);
    $response->assertJsonPath('metadata.cache_ttl', 300);
    $response->assertJsonPath('metadata.timezone', 'Asia/Tehran');
    $response->assertJsonPath('metadata.currency', 'IRR');
});

it('counts a bundle purchase once at its selling price and excludes its component order items', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $order = rankingsOrder(100_000);
    rankingsPay($order, 100_000);
    rankingsBundlePurchase($order, 100_000);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonCount(1, 'data.top_products');
    $response->assertJsonPath('data.top_products.0.name', 'Career Bundle');
    $response->assertJsonPath('data.top_products.0.orders', 1);
    $response->assertJsonPath('data.top_products.0.revenue', 100_000);
    expect($response->json('data.top_products.0.share_pct'))->toEqual(100.0);
});

it('attributes a bundle purchase to the bundle product vendor instead of its component vendors', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $order = rankingsOrder(100_000);
    rankingsPay($order, 100_000);
    ['component_vendors' => $componentVendors] = rankingsBundlePurchase($order, 100_000);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonCount(1, 'data.vendors');
    $response->assertJsonPath('data.vendors.0.name', 'Bundle Vendor');
    $response->assertJsonPath('data.vendors.0.revenue', 100_000);

    $names = collect($response->json('data.vendors'))->pluck('name')->all();

    foreach ($componentVendors as $componentVendor) {
        expect($names)->not->toContain($componentVendor->name);
    }
});

it('nets the component line refunds of a bundle purchase', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $order = rankingsOrder(100_000);
    rankingsPay($order, 100_000);
    rankingsBundlePurchase($order, 100_000, 20_000);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.top_products.0.revenue', 60_000);
});

it('nets total_refunded from a standalone line', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $vendor = Vendor::factory()->create(['name' => 'Refund Vendor']);
    $order  = rankingsOrder(100_000);
    rankingsPay($order, 100_000);
    [, $option] = rankingsProduct($vendor, 'Refunded Product', 100_000);
    rankingsLine($order, $option, $vendor, 100_000, 30_000);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.top_products.0.revenue', 70_000);
});

it('allocates a line value by the paid ratio of a partially paid order', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $vendor = Vendor::factory()->create(['name' => 'Deposit Vendor']);
    $order  = rankingsOrder(200_000);
    rankingsPay($order, 50_000);
    [, $option] = rankingsProduct($vendor, 'Deposit Product', 200_000);
    rankingsLine($order, $option, $vendor, 200_000);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.top_products.0.revenue', 50_000);
    $response->assertJsonPath('data.vendors.0.revenue', 50_000);
});

it('counts one line in both of its categories without deduplicating or setting a category total', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $vendor = Vendor::factory()->create(['name' => 'Category Vendor']);
    $course = Course::factory()->create();

    $first  = Category::factory()->create(['name' => 'Programming']);
    $second = Category::factory()->create(['name' => 'Design']);
    $course->categories()->attach([$first->id, $second->id]);

    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'name'             => 'Overlapping Course',
        'productable_type' => ProductableEnum::COURSE->value,
        'productable_id'   => $course->id,
    ]);
    $option = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 90_000,
    ]);

    $order = rankingsOrder(90_000);
    rankingsPay($order, 90_000);
    rankingsLine($order, $option, $vendor, 90_000);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonCount(2, 'data.categories');

    $categories = collect($response->json('data.categories'))->keyBy('name');

    expect($categories->keys()->sort()->values()->all())->toBe(['Design', 'Programming'])
        ->and($categories['Programming']['revenue'])->toBe(90_000)
        ->and($categories['Design']['revenue'])->toBe(90_000)
        ->and($categories['Programming']['share_pct'])->toEqual(100.0)
        ->and($categories['Design']['share_pct'])->toEqual(100.0);

    expect(array_sum(array_column($response->json('data.categories'), 'share_pct')))->toEqual(200.0);

    $response->assertJsonMissingPath('data.total_revenue');
});

it('attributes a line to the product categories and the productable categories without duplication', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $vendor = Vendor::factory()->create(['name' => 'Catalogue Vendor']);
    $course = Course::factory()->create();

    $shared          = Category::factory()->create(['name' => 'Shared']);
    $productableOnly = Category::factory()->create(['name' => 'Course Only']);
    $productOnly     = Category::factory()->create(['name' => 'Product Only']);

    $course->categories()->attach([$shared->id, $productableOnly->id]);

    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'name'             => 'Two Source Product',
        'productable_type' => ProductableEnum::COURSE->value,
        'productable_id'   => $course->id,
    ]);
    $product->categories()->attach([$shared->id, $productOnly->id]);

    $option = ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => 90_000,
    ]);

    $order = rankingsOrder(90_000);
    rankingsPay($order, 90_000);
    rankingsLine($order, $option, $vendor, 90_000);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonCount(3, 'data.categories');

    $categories = collect($response->json('data.categories'))->keyBy('name');

    expect($categories->keys()->sort()->values()->all())->toBe(['Course Only', 'Product Only', 'Shared'])
        ->and($categories['Shared']['revenue'])->toBe(90_000)
        ->and($categories['Shared']['orders'])->toBe(1)
        ->and($categories['Course Only']['revenue'])->toBe(90_000)
        ->and($categories['Product Only']['revenue'])->toBe(90_000);
});

it('caps top products at six rows', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $vendor = Vendor::factory()->create(['name' => 'Catalogue Vendor']);
    $order  = rankingsOrder(280_000);
    rankingsPay($order, 280_000);

    foreach (range(1, 7) as $index) {
        [, $option] = rankingsProduct($vendor, 'Product '.$index, $index * 10_000);
        rankingsLine($order, $option, $vendor, $index * 10_000);
    }

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonCount(6, 'data.top_products');

    $products = $response->json('data.top_products');

    expect(collect($products)->pluck('name')->all())
        ->toBe(['Product 7', 'Product 6', 'Product 5', 'Product 4', 'Product 3', 'Product 2'])
        ->and($products[0]['share_pct'])->toEqual(25.0)
        ->and(round(array_sum(array_column($products, 'share_pct')), 1))->toEqual(96.4);
});

it('caps vendors at four rows', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $order = rankingsOrder(150_000);
    rankingsPay($order, 150_000);

    foreach (range(1, 5) as $index) {
        $vendor     = Vendor::factory()->create(['name' => 'Vendor '.$index]);
        [, $option] = rankingsProduct($vendor, 'Vendor Product '.$index, $index * 10_000);
        rankingsLine($order, $option, $vendor, $index * 10_000);
    }

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonCount(4, 'data.vendors');

    $vendors = $response->json('data.vendors');

    expect(collect($vendors)->pluck('name')->all())
        ->toBe(['Vendor 5', 'Vendor 4', 'Vendor 3', 'Vendor 2'])
        ->and($vendors[0]['share_pct'])->toEqual(33.3)
        ->and(round(array_sum(array_column($vendors, 'share_pct')), 1))->toEqual(93.3);
});

it('leaves a line with no vendor out of the vendor share of the cohort total', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $vendor = Vendor::factory()->create(['name' => 'Attributed Vendor']);
    $order  = rankingsOrder(100_000);
    rankingsPay($order, 100_000);

    [, $attributedOption] = rankingsProduct($vendor, 'Attributed Product', 60_000);
    rankingsLine($order, $attributedOption, $vendor, 60_000);

    [, $unattributedOption] = rankingsProduct($vendor, 'Unattributed Product', 40_000);
    OrderItem::factory()->create([
        'order_id'                   => $order->id,
        'product_delivery_option_id' => $unattributedOption->id,
        'vendor_id'                  => null,
        'name'                       => $unattributedOption->name,
        'price'                      => 40_000,
        'total'                      => 40_000,
        'payment_type'               => OrderItemPaymentTypeEnum::FULL_PAYMENT,
        'status'                     => OrderItemStatusEnum::COMPLETED,
        'total_refunded'             => 0,
    ]);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonCount(1, 'data.vendors');
    $response->assertJsonPath('data.vendors.0.name', 'Attributed Vendor');
    $response->assertJsonPath('data.vendors.0.revenue', 60_000);
    $response->assertJsonPath('data.vendors.0.share_pct', 60.0);
});

it('excludes free orders from every ranking', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $vendor = Vendor::factory()->create(['name' => 'Free Vendor']);
    $order  = rankingsOrder(0);
    rankingsPay($order, 0, PaymentMethodEnum::NO_PAYMENT);
    [, $option] = rankingsProduct($vendor, 'Free Product', 0);
    rankingsLine($order, $option, $vendor, 0);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonPath('data.top_products', []);
    $response->assertJsonPath('data.categories', []);
    $response->assertJsonPath('data.vendors', []);
});

it('counts the distinct orders a product appears in', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $vendor     = Vendor::factory()->create(['name' => 'Repeat Vendor']);
    [, $option] = rankingsProduct($vendor, 'Repeat Product', 10_000);

    $firstOrder = rankingsOrder(10_000);
    rankingsPay($firstOrder, 10_000);
    rankingsLine($firstOrder, $option, $vendor, 10_000);

    $secondOrder = rankingsOrder(10_000);
    rankingsPay($secondOrder, 10_000);
    rankingsLine($secondOrder, $option, $vendor, 10_000);

    $response = getJson(RANKINGS_ENDPOINT);

    $response->assertOk();
    $response->assertJsonCount(1, 'data.top_products');
    $response->assertJsonPath('data.top_products.0.orders', 2);
    $response->assertJsonPath('data.top_products.0.revenue', 20_000);
});

it('rejects an invalid period', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);

    getJson(RANKINGS_ENDPOINT.'?period=90d')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['period']);
});

it('serves cached rankings until fresh=1 recomputes them', function (): void {
    $this->authorized_user([PermissionEnum::DASHBOARD_FINANCIALS->value]);
    $vendor = Vendor::factory()->create(['name' => 'Cache Vendor']);
    $order  = rankingsOrder(10_000);
    rankingsPay($order, 10_000);
    [, $option] = rankingsProduct($vendor, 'First Product', 10_000);
    rankingsLine($order, $option, $vendor, 10_000);

    $first = getJson(RANKINGS_ENDPOINT);
    $first->assertOk();
    $first->assertJsonPath('data.top_products.0.revenue', 10_000);
    $first->assertJsonPath('metadata.cached', false);

    $secondOrder = rankingsOrder(25_000);
    rankingsPay($secondOrder, 25_000);
    [, $secondOption] = rankingsProduct($vendor, 'Second Product', 25_000);
    rankingsLine($secondOrder, $secondOption, $vendor, 25_000);

    $cached = getJson(RANKINGS_ENDPOINT);
    $cached->assertOk();
    $cached->assertJsonCount(1, 'data.top_products');
    $cached->assertJsonPath('data.top_products.0.revenue', 10_000);
    $cached->assertJsonPath('metadata.cached', true);

    $fresh = getJson(RANKINGS_ENDPOINT.'?fresh=1');
    $fresh->assertOk();
    $fresh->assertJsonCount(2, 'data.top_products');
    $fresh->assertJsonPath('data.top_products.0.name', 'Second Product');
    $fresh->assertJsonPath('data.top_products.0.revenue', 25_000);
    $fresh->assertJsonPath('data.top_products.0.share_pct', 71.4);
    $fresh->assertJsonPath('metadata.cached', false);
});
