<?php

declare(strict_types=1);

use App\Enums\Content\PublicationStatusEnum;
use App\Enums\Order\DiscountTypeEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Payment\PaymentMethodEnum;
use App\Enums\PermissionEnum;
use App\Enums\System\MorphTypeEnum;
use App\Enums\Wallet\TransactionSourceEnum;
use App\Jobs\Provisioning\ProvisionEnrollmentProviderJob;
use App\Models\Course;
use App\Models\DiscountPromotion;
use App\Models\DiscountPromotionRule;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductDeliveryOption;
use App\Models\Term;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WalletTransaction;
use App\Services\Payment\MellatGatewayPaymentProcessor;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Fakes\Payment\MockMultiStepProcessor;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(Tests\Support\Traits\AuthTestTrait::class);

beforeEach(function (): void {
    Queue::fake([
        ProvisionEnrollmentProviderJob::class,
    ]);
});

function createRewardPromotion(string $handler, array $configuration, string $name = 'Reward Promotion'): DiscountPromotion
{
    $promotion = DiscountPromotion::create([
        'name'                             => $name,
        'type'                             => DiscountTypeEnum::CART_CHECKOUT,
        'is_active'                        => true,
        'priority'                         => 1,
        'starts_at'                        => now()->subDay(),
        'ends_at'                          => now()->addDay(),
        'stop_processing_subsequent_rules' => false,
        'requires_coupon'                  => false,
    ]);

    DiscountPromotionRule::create([
        'discount_promotion_id' => $promotion->id,
        'type'                  => 'action',
        'handler'               => $handler,
        'configuration'         => $configuration,
    ]);

    return $promotion;
}

function createRewardDeliveryOption(int $price = 100000): ProductDeliveryOption
{
    $vendor  = Vendor::factory()->create();
    $term    = Term::factory()->create();
    $course  = Course::factory()->create(['status' => PublicationStatusEnum::PUBLISHED]);
    $product = Product::factory()->create([
        'vendor_id'        => $vendor->id,
        'term_id'          => $term->id,
        'productable_id'   => $course->id,
        'productable_type' => MorphTypeEnum::COURSE->value,
        'status'           => PublicationStatusEnum::PUBLISHED,
        'is_visible'       => true,
    ]);

    return ProductDeliveryOption::factory()->create([
        'product_id' => $product->id,
        'price'      => $price,
        'capacity'   => 10,
        'uuid'       => Str::uuid()->toString(),
        'status'     => PublicationStatusEnum::PUBLISHED,
    ]);
}

describe('Promotion rewards are granted at order completion, not during calculation', function (): void {

    test('pricing a cart grants no wallet credit, however many times it is priced', function (): void {
        $option = createRewardDeliveryOption();
        createRewardPromotion('add_wallet_credit', ['amount' => 5000, 'per_item' => false]);

        $customer = User::factory()->create();
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        // Opening the cart repeatedly must be free of money side effects.
        getJson(route('api.v1.shop.cart.index'))->assertOk();
        getJson(route('api.v1.shop.cart.index'))->assertOk();

        expect(WalletTransaction::query()->where('user_id', $customer->id)->count())->toBe(0);
        expect($customer->wallet->refresh()->gift_balance)->toBe(0);
    });

    test('a wallet checkout that completes instantly grants the reward exactly once', function (): void {
        $option = createRewardDeliveryOption();
        createRewardPromotion('add_wallet_credit', ['amount' => 5000, 'per_item' => false]);

        $customer = User::factory()->create();
        $customer->wallet->update(['balance' => 500000]);
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => PaymentMethodEnum::WALLET->value,
        ])->assertCreated();

        $orderId = $response->json('data.order.id');
        expect(Order::findOrFail($orderId)->status)->toBe(OrderStatusEnum::COMPLETED);

        $transactions = WalletTransaction::query()
            ->where('user_id', $customer->id)
            ->where('source_type', TransactionSourceEnum::PROMOTION->value)
            ->get();
        expect($transactions)->toHaveCount(1);
        expect($transactions->first()->amount)->toBe(5000);
        expect($transactions->first()->source_id)->toBe(
            DiscountPromotion::query()->where('name', 'Reward Promotion')->value('id')
        );
        expect($transactions->first()->metadata['order_id'])->toBe($orderId);

        expect($customer->wallet->refresh()->gift_balance)->toBe(5000);
    });

    test('a pending order grants nothing until completion, and completing twice grants once', function (): void {
        $this->authorized_user([PermissionEnum::ORDER_UPDATE]);

        $option = createRewardDeliveryOption();
        createRewardPromotion('add_gift_credit', ['amount' => 7000, 'per_item' => false, 'expires_days' => 30]);

        $customer = User::factory()->create();
        $this->customer($customer);

        postJson(route('api.v1.shop.cart.items.store'), [
            'product_delivery_option_uuid' => $option->uuid,
            'quantity'                     => 1,
        ])->assertOk();

        // A multi-step gateway checkout leaves the order PENDING.
        $this->instance(MellatGatewayPaymentProcessor::class, new MockMultiStepProcessor());
        $response = postJson(route('api.v1.shop.checkout'), [
            'payment_method' => PaymentMethodEnum::MELLAT_GATEWAY->value,
        ])->assertCreated();

        $order = Order::findOrFail($response->json('data.order.id'));
        expect($order->status)->toBe(OrderStatusEnum::PENDING);
        expect(WalletTransaction::query()->where('user_id', $customer->id)->count())->toBe(0);

        putJson("/api/v1/admin/orders/{$order->id}", [
            'status' => OrderStatusEnum::COMPLETED->value,
        ])->assertOk();

        expect($order->refresh()->status)->toBe(OrderStatusEnum::COMPLETED);
        expect(WalletTransaction::query()->where('user_id', $customer->id)->count())->toBe(1);
        expect($customer->wallet->refresh()->gift_balance)->toBe(7000);

        // A second completion path must not pay the reward again.
        putJson("/api/v1/admin/orders/{$order->id}", [
            'status' => OrderStatusEnum::COMPLETED->value,
        ])->assertOk();

        expect(WalletTransaction::query()->where('user_id', $customer->id)->count())->toBe(1);
        expect($customer->wallet->refresh()->gift_balance)->toBe(7000);
    });
});
