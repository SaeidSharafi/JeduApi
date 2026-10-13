<?php

declare(strict_types=1);

namespace App\Contracts\Discounts\Cart;

/**
 * Marker for cart actions whose only pricing effect is reducing line totals.
 *
 * Such actions must apply their markdown through
 * {@see \App\Data\Admin\Discounts\CalculatedOrderItemData::applyDiscount()},
 * which refuses to touch bundle and prepayment lines. An action that grants a
 * non-markdown effect (a gift line, wallet credit) must not implement this
 * contract, because a promotion made only of these actions can do nothing at
 * all on a cart whose every line is a deposit or a bundle.
 */
interface LineMarkdownActionContract {}
