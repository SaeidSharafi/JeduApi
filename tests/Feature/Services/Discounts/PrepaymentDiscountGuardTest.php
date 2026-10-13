<?php

declare(strict_types=1);

use App\Models\ProductDeliveryOption;
use App\Services\Discounts\Cart\Actions\GiftProductAction;
use App\Services\Discounts\Configs\AddGiftCreditConfigData;
use App\Services\Discounts\Configs\AddWalletCreditConfigData;
use App\Services\Discounts\Configs\ApplyFixedAmountOffData;
use App\Services\Discounts\Configs\ApplyPercentageDiscountConfigData;
use App\Services\Discounts\Configs\ApplyTieredPercentageOffData;
use App\Services\Discounts\Configs\GiftProductData;
use App\Services\Discounts\DiscountHandlerRegistry;
use Spatie\LaravelData\Data;

/*
 * Ticket #152 mandates the scope of this file: one behavioural test that walks
 * every registered cart action and asserts a deposit-only cart stays unmarked,
 * plus the fixture-completeness guard that fails when a newly registered action
 * has no fixture. Those two assertions are why this is intentionally a
 * multi-class, registry-driven file rather than a single-handler test.
 */

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

it('leaves a deposit-only cart unmarked by every markdown action', function (string $handlerKey): void {
    $registry     = app(DiscountHandlerRegistry::class);
    $handlerClass = $registry->getCartActionHandler($handlerKey);

    expect($handlerClass)->not->toBeNull();

    $context = discountDepositOnlyContext();
    $line    = $context->items->first();

    app($handlerClass)->apply($context, cartActionFixtures()[$handlerKey]);

    // Only the deposit line is asserted: a gift line is a legitimate added line,
    // never a markdown of the deposit.
    expect($line->discount_amount)->toBe(0)
        ->and($line->total)->toBe(50000);
})->with([
    'apply_fixed_amount_off',
    'apply_percentage_off',
    'apply_tiered_percentage_off',
    'gift_product',
    'add_wallet_credit',
    'add_gift_credit',
]);

it('grants the gift coupon as a free line rather than a deposit markdown', function (): void {
    $context = discountDepositOnlyContext();
    $line    = $context->items->first();

    app(GiftProductAction::class)
        ->apply($context, cartActionFixtures()['gift_product']);

    expect($context->items)->toHaveCount(2)
        ->and($context->items[0]->discount_amount)->toBe(0)
        ->and($context->items[0]->total)->toBe($line->total)
        ->and($context->items[1]->is_gift)->toBeTrue()
        ->and($context->items[1]->discount_amount)->toBeGreaterThan(0);
});
