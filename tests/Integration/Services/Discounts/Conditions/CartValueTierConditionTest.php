<?php

declare(strict_types=1);

use App\Services\Discounts\Cart\Conditions\CartValueTierCondition;
use App\Services\Discounts\Configs\TierData;
use Spatie\LaravelData\Data;

it('publishes the highest satisfied tier and reports eligible', function (): void {
    $handler = new CartValueTierCondition();
    $context = discountOrderContext([discountFullPaymentLine(6000000)]);

    expect($handler->passes($context, discountTierConditionConfig()))->toBeTrue();

    $selection = $context->publishedSelection(CartValueTierCondition::HANDLER_KEY);

    expect($selection)->toBeInstanceOf(TierData::class)
        ->and($selection->min_amount)->toBe(5000000)
        ->and($selection->percentage)->toBe(8.0);
});

it('selects exactly one tier when two rungs are satisfied, never a sum of both', function (): void {
    $handler = new CartValueTierCondition();
    $context = discountOrderContext([discountFullPaymentLine(6000000)]);

    $handler->passes($context, discountTierConditionConfig());

    $selection = $context->publishedSelection(CartValueTierCondition::HANDLER_KEY);

    expect($selection)->toBeInstanceOf(TierData::class)
        ->and($selection->min_amount)->toBe(5000000)
        ->and($selection->percentage)->toBe(8.0)
        ->and($selection->percentage)->not->toBe(13.0);
});

it('is ineligible below the lowest tier and publishes no selection', function (): void {
    $handler = new CartValueTierCondition();
    $context = discountOrderContext([discountFullPaymentLine(2999999)]);

    expect($handler->passes($context, discountTierConditionConfig()))->toBeFalse()
        ->and($context->publishedSelection(CartValueTierCondition::HANDLER_KEY))->toBeNull();
});

it('measures the discountable lines only, so a large deposit cannot select a tier', function (): void {
    $handler = new CartValueTierCondition();

    $deposit = discountPrepaymentLine(price: 20000000, prepaymentAmount: 20000000);
    $fullPayment = discountFullPaymentLine(1000000);

    // The context's all-lines total would clear the top rung; the discountable
    // total (1,000,000) does not even reach the lowest one.
    $context = discountOrderContext([$deposit, $fullPayment], baseSubtotalAllItems: 21000000);

    expect($context->discountableTotal())->toBe(1000000)
        ->and($handler->passes($context, discountTierConditionConfig()))->toBeFalse()
        ->and($context->publishedSelection(CartValueTierCondition::HANDLER_KEY))->toBeNull();
});

it('never measures base prices, only the effective discountable worth', function (): void {
    $handler = new CartValueTierCondition();

    // A line whose base price (5,000,000) would clear the top rung but whose
    // product-discounted total (1,000,000) does not reach the lowest one.
    $discounted = discountFullPaymentLine(price: 5000000, total: 1000000);
    $context    = discountOrderContext([$discounted], baseSubtotalAllItems: 5000000);

    expect($handler->passes($context, discountTierConditionConfig()))->toBeFalse()
        ->and($context->publishedSelection(CartValueTierCondition::HANDLER_KEY))->toBeNull();
});

it('ignores bundle lines while tiering the standalone discountable lines', function (): void {
    $handler = new CartValueTierCondition();

    $standalone = discountFullPaymentLine(3500000);
    $bundle     = discountBundleLine(5000000);

    $context = discountOrderContext([$standalone, $bundle], baseSubtotalAllItems: 8500000);

    expect($handler->passes($context, discountTierConditionConfig()))->toBeTrue();

    $selection = $context->publishedSelection(CartValueTierCondition::HANDLER_KEY);

    expect($selection)->toBeInstanceOf(TierData::class)
        ->and($selection->min_amount)->toBe(3000000)
        ->and($selection->percentage)->toBe(5.0);
});

it('returns false when the configuration is not the correct type', function (): void {
    $handler = new CartValueTierCondition();
    $context = discountOrderContext([discountFullPaymentLine(6000000)]);

    $wrongConfig = new class extends Data
    {
        public function __construct() {}
    };

    expect($handler->passes($context, $wrongConfig))->toBeFalse()
        ->and($context->publishedSelection(CartValueTierCondition::HANDLER_KEY))->toBeNull();
});
