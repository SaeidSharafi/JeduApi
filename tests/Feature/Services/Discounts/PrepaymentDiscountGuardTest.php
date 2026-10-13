<?php

declare(strict_types=1);

use App\Contracts\Discounts\Cart\LineMarkdownActionContract;
use App\Data\Admin\Discounts\CalculatedOrderItemData;
use App\Data\Admin\Discounts\OrderContextData;
use App\Enums\Order\OrderItemPaymentTypeEnum;
use App\Models\DiscountPromotion;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Conditions\CartValueTierCondition;
use App\Services\Discounts\Configs\AddGiftCreditConfigData;
use App\Services\Discounts\Configs\AddWalletCreditConfigData;
use App\Services\Discounts\Configs\ApplyFixedAmountOffData;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;
use App\Services\Discounts\Configs\ApplyTieredPercentageOffData;
use App\Services\Discounts\Configs\GiftProductData;
use App\Services\Discounts\DiscountHandlerRegistry;
use Spatie\LaravelData\Data;

/**
 * A deposit-only cart: one line paid in full is impossible here, so no
 * registered cart action may mark anything down.
 */
function depositOnlyContext(int $prepaymentAmount = 25000, int $price = 100000): OrderContextData
{
    $option = ProductDeliveryOption::factory()->create([
        'price'                   => $price,
        'prepayment_amount'       => $prepaymentAmount,
        'is_prepayment_available' => true,
    ]);

    $customer = User::factory()->create();

    $context = OrderContextData::from([
        'customer' => $customer,
        'items'    => [
            new CalculatedOrderItemData(
                product_delivery_option: $option,
                qty: 2,
                payment_type: OrderItemPaymentTypeEnum::PRE_PAYMENT,
                price: $price,
                total: $prepaymentAmount * 2,
            ),
        ],
        'subtotal_full_payment_items' => 0,
        'subtotal_all_items'          => $price * 2,
    ]);

    $context->evaluating_promotion = DiscountPromotion::factory()->create();

    // Publish a tier selection so the config-free consuming action is genuinely
    // driven; the deposit-only cart still has nothing it may mark down.
    $context->publishSelection(CartValueTierCondition::HANDLER_KEY, [
        'min_amount' => 0,
        'percentage' => 10.0,
    ]);

    return $context;
}

/**
 * The concrete configuration each registered cart action is driven with.
 * The key set must equal the registered key set, so adding an action fails
 * this test until its behaviour on a deposit-only cart is covered.
 *
 * @return array<string, Data>
 */
function cartActionFixtures(): array
{
    return [
        'apply_fixed_amount_off'      => new ApplyFixedAmountOffData(amount: 3000),
        'apply_percentage_off'        => new ApplyPercentageDiscountConfigData(percentage: 10),
        'apply_tiered_percentage_off' => new ApplyTieredPercentageOffData(),
        // The gift line is a free line, not a markdown, so it is tolerated below.
        'gift_product'      => new GiftProductData(product_delivery_option_id: ProductDeliveryOption::factory()->create()->id),
        'add_wallet_credit' => new AddWalletCreditConfigData(amount: 5000),
        'add_gift_credit'   => new AddGiftCreditConfigData(amount: 5000),
    ];
}

it('covers every registered cart action with a fixture', function (): void {
    $registered = array_keys(app(DiscountHandlerRegistry::class)->getCartActionHandlers());

    sort($registered);

    $covered = array_keys(cartActionFixtures());
    sort($covered);

    expect($covered)->toBe($registered);
});

it('leaves a deposit-only cart unmarked by every markdown action', function (string $handlerKey, bool $isLineMarkdown): void {
    $registry     = app(DiscountHandlerRegistry::class);
    $handlerClass = $registry->getCartActionHandler($handlerKey);

    expect($handlerClass)->not->toBeNull()
        ->and(is_subclass_of($handlerClass, LineMarkdownActionContract::class))->toBe($isLineMarkdown);

    $context = depositOnlyContext();
    $line    = $context->items->first();

    app($handlerClass)->apply($context, cartActionFixtures()[$handlerKey]);

    // Only the deposit line is asserted: a gift line is a legitimate added line,
    // never a markdown of the deposit.
    expect($line->discount_amount)->toBe(0)
        ->and($line->total)->toBe(50000);
})->with([
    'apply_fixed_amount_off'      => ['apply_fixed_amount_off', true],
    'apply_percentage_off'        => ['apply_percentage_off', true],
    'apply_tiered_percentage_off' => ['apply_tiered_percentage_off', true],
    'gift_product'                => ['gift_product', false],
    'add_wallet_credit'           => ['add_wallet_credit', false],
    'add_gift_credit'             => ['add_gift_credit', false],
]);

it('grants the gift coupon as a free line rather than a deposit markdown', function (): void {
    $context = depositOnlyContext();
    $line    = $context->items->first();

    app(App\Services\Discounts\Cart\Actions\GiftProductAction::class)
        ->apply($context, cartActionFixtures()['gift_product']);

    expect($context->items)->toHaveCount(2)
        ->and($context->items[0]->discount_amount)->toBe(0)
        ->and($context->items[0]->total)->toBe($line->total)
        ->and($context->items[1]->is_gift)->toBeTrue()
        ->and($context->items[1]->discount_amount)->toBeGreaterThan(0);
});
