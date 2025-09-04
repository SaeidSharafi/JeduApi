<?php

declare(strict_types=1);

return [
    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
        'insufficient_balance_with_info' => 'Insufficient wallet balance. Available: :available, Required: :required',
        'product_delivery_option_check_rule' => [
            'invalid_delivery_method' => 'The :delivery_method is not valid for the product type :fulfillment_type',
        ],
        'product_delivery_option' => [
            'details_json' => [
                'array' => 'The product details must be an array and its fields must match the product type.',
            ],
        ],
        'civil_id' => [
            'wrong' => 'the :type is invalid, please enter a valid :type',
        ],
        'wallet_not_found' => 'Wallet not found for the specified user.',
        'user_not_found' => 'User not found.',
        'wallet_already_exists' => 'Wallet already exists for this user.',
        'insufficient_balance' => 'Insufficient wallet balance for this transaction.',

        // Wallet Campaign validation messages
        'campaign_not_found' => 'Wallet campaign not found.',
        'campaign_not_active' => 'Campaign is not currently active.',
        'user_not_eligible' => 'User is not eligible for this campaign.',
        'duplicate_allocation' => 'Gift credit already allocated for this campaign and user.',
        'campaign_expired' => 'This campaign has expired.',
        'usage_limit_reached' => 'Campaign usage limit has been reached.',
        'already_claimed' => 'You have already claimed this campaign bonus.',
        'wallet_not_active' => 'User wallet is not active.',
    ],
];
