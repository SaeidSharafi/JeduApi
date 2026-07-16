<?php

declare(strict_types=1);

use App\Data\Admin\Discounts\OrderContextData;
use App\Models\ProductDeliveryOption;
use App\Models\User;
use App\Services\Discounts\Cart\Actions\GiftProductAction;
use App\Services\Discounts\Configs\GiftProductData;

describe('GiftProductAction', function (): void {
    test('it adds a gift item to the cart context successfully', function (): void {
        $action = new GiftProductAction();
        $giftOption = ProductDeliveryOption::factory()->create(['price' => 5000]);
        $config = new GiftProductData(product_delivery_option_id: $giftOption->id);

        $context = OrderContextData::from([
            'customer' => User::factory()->make(),
            'items' => collect([]),
            'subtotal_full_payment_items' => 10000,
            'subtotal_all_items' => 10000,
        ]);

        $action->apply($context, $config);

        // Assert gift was added
        expect($context->items)->toHaveCount(1)
            ->and($context->items->first()->is_gift)->toBeTrue()
            ->and($context->items->first()->total)->toBe(0) // Free
            ->and($context->items->first()->discount_amount)->toBe(5000)
            ->and($context->subtotal_all_items)->toBe(15000); // Audit trail subtotal increased
    });

    test('it prevents adding the exact same gift twice', function (): void {
        $action = new GiftProductAction();
        $giftOption = ProductDeliveryOption::factory()->create(['price' => 5000]);
        $config = new GiftProductData(product_delivery_option_id: $giftOption->id);

        $context = OrderContextData::from([
            'customer' => User::factory()->make(),
            'items' => collect([]),
            'subtotal_full_payment_items' => 0,
            'subtotal_all_items' => 0,
        ]);

        // Apply twice
        $action->apply($context, $config);
        $action->apply($context, $config);

        // Still only 1 gift
        expect($context->items)->toHaveCount(1);
    });
});
