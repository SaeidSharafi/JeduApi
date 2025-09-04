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
    ],
];
