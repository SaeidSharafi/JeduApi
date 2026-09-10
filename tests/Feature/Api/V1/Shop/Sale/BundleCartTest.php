<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\EnrollmentStatusEnum;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Enums\Product\DeliveryMethodEnum;
use App\Enums\Product\FulfillmentTypeEnum;
use App\Enums\Product\ProductableEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Bundle;
use App\Models\CartItem;
use App\Models\Course;
use App\Models\DiscountCoupon;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Enrollment;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\ProductDeliveryOptionDiscountPrice;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Traits\AuthTestTrait;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(AuthTestTrait::class);

/**
 * Create a published Course Productable Product with two alternate published,
 * open, uncapped PDOs for the same underlying productable.
 *
 * @return array{product: Product, pdo_a: ProductDeliveryOption, pdo_b: ProductDeliveryOption}
 */
function v11CoursePair(int $price = 100000): array
{
    $course  = Course::factory()->create();
    $product = Product::factory()->create([
        'productable_type' => ProductableEnum::COURSE->value,
        'productable_id'   => $course->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);

    $make = fn (): ProductDeliveryOption => ProductDeliveryOption::factory()->create([
        'product_id'              => $product->id,
        'price'                   => $price,
        'status'                  => PublicationStatusEnum::PUBLISHED,
        'capacity'                => null,
        'registration_start_date' => null,
        'registration_end_date'   => null,
        'available_from'          => null,
        'available_to'            => null,
    ]);

    return ['product' => $product, 'pdo_a' => $make(), 'pdo_b' => $make()];
}

/**
 * Build a reviewed, on-sale Bundle PDO wrapping the given component PDOs.
 * The Bundle selling price equals the sum of the supplied allocations.
 *
 * @param  array<int, array{pdo: ProductDeliveryOption, allocation: int}>  $components
 */
function v11BundleFor(array $components): ProductDeliveryOption
{
    $bundle        = Bundle::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $bundleProduct = Product::factory()->create([
        'productable_type' => ProductableEnum::BUNDLE->value,
        'productable_id'   => $bundle->id,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);
    $sellingPrice = (int) collect($components)->sum('allocation');
    $bundleOption = ProductDeliveryOption::factory()->create([
        'product_id'              => $bundleProduct->id,
        'fulfillment_type'        => FulfillmentTypeEnum::COMPOSITE,
        'delivery_method'         => DeliveryMethodEnum::BUNDLE,
        'details_json'            => [],
        'price'                   => $sellingPrice,
        'status'                  => PublicationStatusEnum::PUBLISHED,
        'capacity'                => null,
        'registration_start_date' => null,
        'registration_end_date'   => null,
        'available_from'          => null,
        'available_to'            => null,
    ]);

    foreach ($components as $component) {
        $bundleOption->bundleComponents()->attach($component['pdo']->id, ['allocation' => $component['allocation']]);
    }

    return $bundleOption;
}

beforeEach(function (): void {
    Queue::fake([ProvisionEnrollmentProviderJob::class]);
});

it('adds a reviewed Bundle PDO as one cart line priced at its reviewed selling price', function (): void {
    $course       = v11CoursePair(120000);
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 120000]]);
    $user         = User::factory()->create();
    $this->customer($user);

    $response = postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.total_items_count', 1)
        ->assertJsonPath('data.items.0.quantity', 1)
        ->assertJsonPath('data.items.0.current_price', 120000)
        ->assertJsonPath('data.items.0.line_total', 120000)
        ->assertJsonCount(1, 'data.items');

    assertDatabaseCount('cart_items', 1);
    assertDatabaseHas('cart_items', [
        'product_delivery_option_id' => $bundleOption->id,
        'quantity'                   => 1,
        'composition_version'        => $bundleOption->fresh()->composition_version,
    ]);
});

it('rejects Bundle quantities above one on add and on update', function (): void {
    $course       = v11CoursePair();
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 100000]]);
    $user         = User::factory()->create();
    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 2,
    ])->assertUnprocessable()->assertJsonValidationErrors(['quantity']);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $cartItem = CartItem::query()
        ->where('product_delivery_option_id', $bundleOption->id)
        ->firstOrFail();

    putJson(route('api.v1.shop.cart.items.update', $cartItem), [
        'quantity'     => 2,
        'payment_type' => OrderItemPaymentTypeEnum::FULL_PAYMENT->value,
    ])->assertUnprocessable()->assertJsonValidationErrors(['product_delivery_option_uuid']);

    assertDatabaseHas('cart_items', [
        'id'       => $cartItem->id,
        'quantity' => 1,
    ]);
});

it('keeps unrelated standalone items and multiple non-overlapping Bundles in one cart', function (): void {
    $courseOne        = v11CoursePair(100000);
    $courseTwo        = v11CoursePair(100000);
    $standaloneCourse = v11CoursePair(100000);
    $bundleOne        = v11BundleFor([['pdo' => $courseOne['pdo_a'], 'allocation' => 150000]]);
    $bundleTwo        = v11BundleFor([['pdo' => $courseTwo['pdo_a'], 'allocation' => 250000]]);
    $user             = User::factory()->create();
    $this->customer($user);

    collect([$bundleOne, $standaloneCourse['pdo_a'], $bundleTwo])->each(function (ProductDeliveryOption $option): void {
        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();
    });

    $data = getJson(route('api.v1.shop.cart.index'))->assertOk()->json('data');

    expect($data['total_items_count'])->toBe(3)
        ->and($data['subtotal'])->toBe(150000 + 100000 + 250000)
        ->and($data['grand_total'])->toBe(150000 + 100000 + 250000);

    assertDatabaseCount('cart_items', 3);
});

it('rejects a second Bundle overlapping an underlying Productable already in the cart', function (): void {
    $course    = v11CoursePair();
    $bundleOne = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 100000]]);
    $bundleTwo = v11BundleFor([['pdo' => $course['pdo_b'], 'allocation' => 100000]]);
    $user      = User::factory()->create();
    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOne->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleTwo->uuid,
        'quantity'                     => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

    assertDatabaseCount('cart_items', 1);
});

it('rejects a standalone item overlapping a Bundle already in the cart', function (): void {
    $course       = v11CoursePair();
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 100000]]);
    $user         = User::factory()->create();
    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $course['pdo_b']->uuid,
        'quantity'                     => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['items']);
});

it('rejects a Bundle whose component Productable the Customer already owns', function (): void {
    $course       = v11CoursePair();
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 100000]]);
    $customer     = User::factory()->create();
    Enrollment::factory()->create([
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $course['pdo_b']->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);
    $this->customer($customer);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

    assertDatabaseCount('cart_items', 0);
});

it('rejects a Bundle added after a standalone line already overlaps its component Productable', function (): void {
    $course       = v11CoursePair();
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_b'], 'allocation' => 100000]]);
    $user         = User::factory()->create();
    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $course['pdo_a']->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

    assertDatabaseCount('cart_items', 1);
});

it('removes a Bundle line cleanly from a mixed cart', function (): void {
    $course       = v11CoursePair();
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 150000]]);
    $standalone   = v11CoursePair(100000);
    $user         = User::factory()->create();
    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $standalone['pdo_a']->uuid,
        'quantity'                     => 1,
    ])->assertOk();
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $bundleItemId = CartItem::query()
        ->where('product_delivery_option_id', $bundleOption->id)
        ->value('id');

    deleteJson(route('api.v1.shop.cart.items.destroy', $bundleItemId))->assertNoContent();

    $data = getJson(route('api.v1.shop.cart.index'))->assertOk()->json('data');

    expect($data['total_items_count'])->toBe(1)
        ->and($data['items'][0]['sku'])->toBe($standalone['pdo_a']->sku)
        ->and($data['grand_total'])->toBe(100000);

    assertDatabaseCount('cart_items', 1);

    // Re-adding after removal restores a fresh snapshot.
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk()
        ->assertJsonPath('data.total_items_count', 2);
});

it('keeps a multi-component Bundle as one line at the sum of its fixed allocations', function (): void {
    $courseOne    = v11CoursePair(100000);
    $courseTwo    = v11CoursePair(200000);
    $bundleOption = v11BundleFor([
        ['pdo' => $courseOne['pdo_a'], 'allocation' => 80000],
        ['pdo' => $courseTwo['pdo_a'], 'allocation' => 120000],
    ]);
    $user = User::factory()->create();
    $this->customer($user);

    $response = postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.total_items_count', 1)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.current_price', 200000)
        ->assertJsonPath('data.items.0.line_total', 200000)
        ->assertJsonPath('data.grand_total', 200000);

    assertDatabaseCount('cart_items', 1);
    expect($bundleOption->bundleComponents()->get()->map(fn ($c): int => (int) $c->pivot->allocation)->sort()->values()->all())
        ->toBe([80000, 120000]);
});

it('rejects checkout when a Bundle component became unavailable after the Bundle was added', function (): void {
    $course       = v11CoursePair();
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 200000]]);
    $user         = User::factory()->create();
    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $course['pdo_a']->update(['status' => PublicationStatusEnum::ARCHIVED]);

    postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
        'payment_data'   => [
            'transaction_id'   => 'unavailable-1',
            'transaction_date' => verta()->formatDate(),
            'sender_name'      => 'John Doe',
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.0']);

    assertDatabaseHas('cart_items', [
        'product_delivery_option_id' => $bundleOption->id,
        'quantity'                   => 1,
    ]);
});

it('rejects a standalone purchase of a Productable already owned through a Bundle component', function (): void {
    $course       = v11CoursePair();
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 100000]]);
    $customer     = User::factory()->create();
    Enrollment::factory()->create([
        'customer_id'                => $customer->id,
        'product_delivery_option_id' => $course['pdo_a']->id,
        'enrollment_status'          => EnrollmentStatusEnum::ACTIVE,
    ]);
    $this->customer($customer);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $course['pdo_b']->uuid,
        'quantity'                     => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['items']);
});

it('keeps a Bundle line at its reviewed selling price when its component carries a standalone discount', function (): void {
    $course = v11CoursePair(100000);
    ProductDeliveryOptionDiscountPrice::factory()->create([
        'product_delivery_option_id' => $course['pdo_a']->id,
        'discount_promotion_id'      => DiscountPromotion::factory()->create()->id,
        'discounted_price'           => 70000,
        'starts_at'                  => now()->subDay(),
        'ends_at'                    => now()->addDay(),
    ]);
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 100000]]);
    $user         = User::factory()->create();
    $this->customer($user);

    $response = postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.items.0.current_price', 100000)
        ->assertJsonPath('data.items.0.original_price', 100000)
        ->assertJsonPath('data.items.0.product_discount_amount', 0)
        ->assertJsonPath('data.items.0.line_total', 100000)
        ->assertJsonPath('data.grand_total', 100000);
});

it('applies a coupon to unrelated standalone lines only and never to the Bundle line', function (): void {
    $promotion = DiscountPromotion::factory()->create([
        'type'            => App\Enums\Order\DiscountTypeEnum::CART_CHECKOUT,
        'is_active'       => true,
        'starts_at'       => now()->subDay(),
        'ends_at'         => now()->addDay(),
        'priority'        => 1,
        'requires_coupon' => true,
    ]);
    DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'action',
        'handler'               => 'apply_percentage_off',
        'configuration'         => ['percentage' => 10],
    ]);
    DiscountCoupon::factory()->create([
        'discount_promotion_id' => $promotion->id,
        'code'                  => 'BUNDLE10',
        'is_active'             => true,
    ]);

    $bundleCourse = v11CoursePair(100000);
    $bundleOption = v11BundleFor([['pdo' => $bundleCourse['pdo_a'], 'allocation' => 200000]]);
    $standalone   = v11CoursePair(100000);
    $user         = User::factory()->create();
    $this->customer($user);

    // Standalone line first so the Bundle line is index 1.
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $standalone['pdo_a']->uuid,
        'quantity'                     => 1,
    ])->assertOk();
    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    $data = postJson(route('api.v1.shop.cart.coupon.apply'), [
        'coupon_code' => 'BUNDLE10',
    ])->assertOk()->json('data');

    $lines          = collect($data['items'])->keyBy('product_name');
    $bundleLine     = $lines->first(fn (array $line): bool => $line['product_name'] !== $standalone['product']->name);
    $standaloneLine = $lines[$standalone['product']->name];

    expect($data['subtotal'])->toBe(100000 + 200000)
        ->and($data['discount_amount'])->toBe(10000)
        ->and($data['grand_total'])->toBe(290000)
        ->and($standaloneLine['cart_discount_amount'])->toBe(10000)
        ->and($standaloneLine['line_total'])->toBe(90000)
        ->and($bundleLine['cart_discount_amount'])->toBe(0)
        ->and($bundleLine['total_discount_amount'])->toBe(0)
        ->and($bundleLine['line_total'])->toBe(200000);
});

it('rejects prepayment as a payment mode for a Bundle PDO', function (): void {
    $course       = v11CoursePair();
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 100000]]);
    $user         = User::factory()->create();
    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
        'payment_type'                 => OrderItemPaymentTypeEnum::PRE_PAYMENT->value,
    ])->assertUnprocessable()->assertJsonValidationErrors(['payment_type']);

    assertDatabaseCount('cart_items', 0);
});

it('requires explicit reconfirmation after a Bundle composition change', function (): void {
    $course       = v11CoursePair(200000);
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 200000]]);
    $user         = User::factory()->create();
    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    // Material recomposition by staff: version bumps, option stays on sale.
    $bundleOption->refresh()->update(['composition_version' => $bundleOption->composition_version + 1]);

    postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
        'payment_data'   => [
            'transaction_id'   => 'stale-1',
            'transaction_date' => verta()->formatDate(),
            'sender_name'      => 'John Doe',
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.0']);

    assertDatabaseHas('cart_items', [
        'product_delivery_option_id' => $bundleOption->id,
        'quantity'                   => 1,
    ]);

    // Explicit reconfirmation: remove the stale selection and re-add it.
    $cartItemId = getJson(route('api.v1.shop.cart.index'))->json('data.items.0.id');
    deleteJson(route('api.v1.shop.cart.items.destroy', $cartItemId))->assertNoContent();

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->fresh()->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
        'payment_data'   => [
            'transaction_id'   => 'stale-2',
            'transaction_date' => verta()->formatDate(),
            'sender_name'      => 'John Doe',
        ],
    ])->assertCreated()
        ->assertJsonPath('data.order.grand_total', 200000)
        ->assertJsonCount(1, 'data.order.items')
        ->assertJsonPath('data.order.items.0.type', 'bundle');
});

it('rejects checkout of a Bundle that became review-required after it was added', function (): void {
    $course       = v11CoursePair();
    $bundleOption = v11BundleFor([['pdo' => $course['pdo_a'], 'allocation' => 200000]]);
    $user         = User::factory()->create();
    $this->customer($user);

    postJson(route('api.v1.shop.cart.items.store'), [
        'product_delivery_option_uuid' => $bundleOption->uuid,
        'quantity'                     => 1,
    ])->assertOk();

    // Material component change (#14) removes the Bundle from sale.
    $bundleOption->update(['bundle_review_required_at' => now()]);

    postJson(route('api.v1.shop.checkout'), [
        'payment_method' => 'bank_transfer',
        'payment_data'   => [
            'transaction_id'   => 'review-1',
            'transaction_date' => verta()->formatDate(),
            'sender_name'      => 'John Doe',
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.0']);

    assertDatabaseHas('cart_items', [
        'product_delivery_option_id' => $bundleOption->id,
        'quantity'                   => 1,
    ]);
});
