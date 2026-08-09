<?php

declare(strict_types=1);

return [

    'handlers' => [

        // ---- Cart Conditions ----
        'cart_value_over' => [
            'name'        => 'Minimum Cart Value',
            'description' => 'This condition is evaluated based on the total value of the customer\'s cart.',
            'fields'      => [
                'operator' => [
                    'label'       => 'Comparison Type',
                    'description' => 'How the customer\'s cart value is compared to the specified amount (e.g., greater than or equal, less than, etc.).',
                ],
                'value' => [
                    'label'       => 'Amount',
                    'description' => 'The amount the customer\'s cart is compared against (in Rials).',
                ],
                'include_prepayments' => [
                    'label'       => 'Include Prepayments',
                    'description' => 'If enabled, prepayment amounts of the cart items will also be included in this condition\'s calculation.',
                ],
            ],
        ],

        // ---- Shared by Cart + Product Conditions (same handler key, both contexts) ----
        'product_in_category' => [
            'name'        => 'Product Category',
            'description' => 'This condition checks whether the product (or cart products) are within the specified categories.',
            'fields'      => [
                'category_ids' => [
                    'label'       => 'Categories',
                    'description' => 'The categories the product must belong to for this condition to be met.',
                ],
                'match_policy' => [
                    'label'       => 'Match Policy',
                    'description' => 'Specifies whether the product needs to be in at least one of the selected categories, or must be in all of them.',
                ],
            ],
        ],

        // ---- Cart Actions ----
        'add_gift_credit' => [
            'name'        => 'Grant Gift Credit',
            'description' => 'An amount is added to the customer\'s wallet as gift credit, which can only be used for future purchases.',
            'fields'      => [
                'amount' => [
                    'label'       => 'Gift Credit Amount',
                    'description' => 'The amount deposited into the customer\'s wallet as gift credit (in Rials).',
                ],
                'per_item' => [
                    'label'       => 'Calculate Per Item',
                    'description' => 'If enabled, the specified amount is calculated for each individual item in the cart; otherwise, it is granted once as a flat rate.',
                ],
                'expires_days' => [
                    'label'       => 'Validity Period (Days)',
                    'description' => 'The number of days this gift credit will remain valid after being granted. If left empty, the credit will have no expiration date.',
                ],
                'description' => [
                    'label'       => 'Transaction Note',
                    'description' => 'The text displayed in the customer\'s wallet transaction history for this credit (optional).',
                ],
            ],
        ],

        'add_wallet_credit' => [
            'name'        => 'Increase Wallet Balance',
            'description' => 'A specified amount is directly added to the customer\'s primary wallet balance.',
            'fields'      => [
                'amount' => [
                    'label'       => 'Credit Amount',
                    'description' => 'The amount added to the customer\'s wallet balance (in Rials).',
                ],
                'per_item' => [
                    'label'       => 'Calculate Per Item',
                    'description' => 'If enabled, the specified amount is calculated for each individual item in the cart; otherwise, it is granted once as a flat rate.',
                ],
                'description' => [
                    'label'       => 'Transaction Note',
                    'description' => 'The text displayed in the customer\'s wallet transaction history for this credit (optional).',
                ],
            ],
        ],

        'apply_percentage_off' => [
            'name'        => 'Percentage Discount on Cart',
            'description' => 'A specified percentage is deducted from the total value of cart items.',
            'fields'      => [
                'percentage' => [
                    'label'       => 'Discount Percentage',
                    'description' => 'The discount percentage to be deducted from the items\' total; for example, 15 is equivalent to a 15% discount.',
                ],
            ],
        ],

        // ---- Product Actions ----
        'apply_percentage_off_product' => [
            'name'        => 'Product Percentage Discount',
            'description' => 'A specified percentage is deducted from the product price, and the new price is displayed to the customer on the product page and lists.',
            'fields'      => [
                'percentage' => [
                    'label'       => 'Discount Percentage',
                    'description' => 'The discount percentage deducted from the product price; for example, 15 is equivalent to a 15% discount.',
                ],
            ],
        ],

        'apply_fixed_discount_product' => [
            'name'        => 'Product Fixed Amount Discount',
            'description' => 'A specified fixed amount is deducted from the product price.',
            'fields'      => [
                'amount' => [
                    'label'       => 'Discount Amount',
                    'description' => 'The fixed amount deducted from the product price (in Rials).',
                ],
            ],
        ],
        'specific_products_in_cart' => [
            'name'        => 'Specific Products in Cart',
            'description' => 'This condition checks whether the specified products are present in the cart.',
            'fields'      => [
                'product_ids' => [
                    'label'       => 'Products',
                    'description' => 'The products that must be present in the cart for this condition to be met.',
                ],
                'match_policy' => [
                    'label'       => 'Match Policy',
                    'description' => 'Specifies whether the cart must contain at least one of the selected products, or all of them.',
                ],
            ],
        ],
        'user_never_purchased_category' => [
            'name'        => 'User Never Purchased Category',
            'description' => 'This condition checks that the customer has never purchased from the specified categories.',
            'fields'      => [
                'category_ids' => [
                    'label'       => 'Categories',
                    'description' => 'The categories the customer must not have purchased from.',
                ],
                'match_policy' => [
                    'label'       => 'Match Policy',
                    'description' => 'Specifies whether the customer must not have purchased from at least one of the selected categories, or from none of them.',
                ],
            ],
        ],
        'delivery_method_is' => [
            'name'        => 'Delivery Method',
            'description' => 'This condition checks whether the product\'s delivery method matches the selected options.',
            'fields'      => [
                'delivery_methods' => [
                    'label'       => 'Delivery Methods',
                    'description' => 'The allowed delivery methods for this condition.',
                ],
            ],
        ],
        'price_between' => [
            'name'        => 'Price Between',
            'description' => 'This condition checks whether the product price falls within the specified range.',
            'fields'      => [
                'min_price' => [
                    'label'       => 'Minimum Price',
                    'description' => 'The minimum product price (in Rials).',
                ],
                'max_price' => [
                    'label'       => 'Maximum Price',
                    'description' => 'The maximum product price (in Rials). Leave empty for no upper limit.',
                ],
            ],
        ],
        'low_capacity_remaining' => [
            'name'        => 'Low Capacity Remaining',
            'description' => 'This condition checks whether the remaining capacity of the product is below the specified threshold.',
            'fields'      => [
                'threshold' => [
                    'label'       => 'Capacity Threshold',
                    'description' => 'The capacity utilization ratio at which the condition is triggered (e.g., 80 for 80% full).',
                ],
            ],
        ],
        'registration_closing_soon' => [
            'name'        => 'Registration Closing Soon',
            'description' => 'This condition checks whether the product registration closing date is within the specified number of days.',
            'fields'      => [
                'days' => [
                    'label'       => 'Days Before Closing',
                    'description' => 'The number of days before registration closes at which the condition is triggered.',
                ],
            ],
        ],
        'vendor_is' => [
            'name'        => 'Vendor',
            'description' => 'This condition checks whether the product vendor matches the selected vendors.',
            'fields'      => [
                'vendor_ids' => [
                    'label'       => 'Vendors',
                    'description' => 'The vendors whose products this condition applies to.',
                ],
            ],
        ],
        'apply_fixed_price_product' => [
            'name'        => 'Product Fixed Price',
            'description' => 'Sets a fixed price for the product, overriding the original price.',
            'fields'      => [
                'fixed_price' => [
                    'label'       => 'Fixed Price',
                    'description' => 'The fixed price to set for the product (in Rials).',
                ],
            ],
        ],
        'apply_tiered_percentage_off_product' => [
            'name'        => 'Product Tiered Percentage Discount',
            'description' => 'Applies a tiered percentage discount to the product price based on defined tiers.',
            'fields'      => [
                'tiers' => [
                    'label'       => 'Discount Tiers',
                    'description' => 'The tiers defining amount thresholds and their corresponding discount percentages.',
                ],
            ],
        ],
        'first_order_only' => [
            'name'        => 'First Order Only',
            'description' => 'This condition checks whether this is the customer\'s first order.',
            'fields'      => [],
        ],
        'gift_product' => [
            'name'        => 'Gift Product',
            'description' => 'Adds a product to the cart as a gift.',
            'fields'      => [
                'product_delivery_option_id' => [
                    'label'       => 'Gift Product',
                    'description' => 'The product delivery option to add as a gift.',
                ],
            ],
        ],
        'apply_fixed_amount_off' => [
            'name'        => 'Fixed Amount Off Cart',
            'description' => 'A specified fixed amount is deducted from the cart total.',
            'fields'      => [
                'amount' => [
                    'label'       => 'Discount Amount',
                    'description' => 'The fixed amount deducted from the cart total (in Rials).',
                ],
            ],
        ],
        'apply_tiered_percentage_off' => [
            'name'        => 'Tiered Percentage Off Cart',
            'description' => 'Applies a tiered percentage discount to the cart based on defined tiers.',
            'fields'      => [
                'tiers' => [
                    'label'       => 'Discount Tiers',
                    'description' => 'The tiers defining amount thresholds and their corresponding discount percentages.',
                ],
            ],
        ],
        'cart_item_count_over' => [
            'name'        => 'Cart Item Count Over',
            'description' => 'This condition checks whether the number of items in the cart exceeds the specified threshold.',
            'fields'      => [
                'min_count' => [
                    'label'       => 'Minimum Item Count',
                    'description' => 'The minimum number of items required in the cart for this condition to be met.',
                ],
                'count_quantities' => [
                    'label'       => 'Count Quantities',
                    'description' => 'If enabled, the total quantity of each item is counted; otherwise, only distinct items are counted.',
                ],
            ],
        ],
    ],

    'operators' => [
        'greater_than_or_equal' => 'Greater than or equal',
        'greater_than'          => 'Greater than',
        'less_than_or_equal'    => 'Less than or equal',
        'less_than'             => 'Less than',
        'equal'                 => 'Equal to',
    ],

    'types' => [
        'product_specific' => [
            'label'       => 'Product-Specific Discount',
            'description' => 'This type of discount is applied to selected products and is displayed to the customer on the product page and product lists.',
        ],
        'cart_checkout' => [
            'label'       => 'Checkout-Time Discount',
            'description' => 'This type of discount is calculated and applied at the final stage of purchase based on conditions of the entire cart (such as discount codes or cart value).',
        ],
    ],

    // Reusable enum case labels, keyed by enum class basename — separate from
    // per-field labels since the same enum can be reused across handlers.
    'enum_cases' => [
        'MatchPolicyEnum' => [
            'any' => 'At least one of the selected items',
            'all' => 'All selected items',
        ],
    ],

    'validation' => [
        'missing_required_keys'  => 'The :attribute must include "type", "rule ID", and "configuration".',
        'handler_not_recognized' => 'No condition or action was found with the ID \':handler\'.',
        'configuration_invalid'  => 'The configuration provided for \':handler\' is invalid: :errors',
        'condition_required'     => 'At least one condition must be specified in :attribute.',
        'action_required'        => 'At least one action must be specified in :attribute.',
    ],

];
