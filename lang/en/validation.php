<?php

declare(strict_types=1);

return [
    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
        'insufficient_balance_with_info'     => 'Insufficient wallet balance. Available: :available, Required: :required',
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
        'category' => [
            'only_courses_can_be_good_for_start' => 'Only courses can be marked as good for start.',
        ],
        'wallet_not_found'      => 'Wallet not found for the specified user.',
        'user_not_found'        => 'User not found.',
        'wallet_already_exists' => 'Wallet already exists for this user.',
        'insufficient_balance'  => 'Insufficient wallet balance for this transaction.',

        // Wallet Campaign validation messages
        'campaign_not_found'   => 'Wallet campaign not found.',
        'campaign_not_active'  => 'Campaign is not currently active.',
        'user_not_eligible'    => 'User is not eligible for this campaign.',
        'duplicate_allocation' => 'Gift credit already allocated for this campaign and user.',
        'campaign_expired'     => 'This campaign has expired.',
        'usage_limit_reached'  => 'Campaign usage limit has been reached.',
        'already_claimed'      => 'You have already claimed this campaign bonus.',
        'wallet_not_active'    => 'User wallet is not active.',
    ],

    'attributes' => [
        'contact_info' => [
            'addresses'          => 'Addresses',
            'working_hours'      => 'Working Hours',
            'support_email'      => 'Support Email',
            'social_media_links' => 'Social Media Links',
        ],
        'address_info' => [
            'name'      => 'Name',
            'address'   => 'Address',
            'latitude'  => 'Latitude',
            'longitude' => 'Longitude',
            'phone'     => 'Phone',
        ],
        'social_media' => [
            'platform' => 'Platform',
            'link'     => 'Link',
        ],
        'about_us' => [
            'title'                        => 'Title',
            'main_block_title'             => 'Main Block Title',
            'main_block_content'           => 'Main Block Content',
            'main_block_image'             => 'Main Block Image',
            'images'                       => 'Images',
            'active_course_groups_title'   => 'Active Course Groups Title',
            'active_course_groups_content' => 'Active Course Groups Content',
            'active_course_groups_image'   => 'Active Course Groups Image',
            'capabilities_title'           => 'Capabilities Title',
            'capabilities_content'         => 'Capabilities Content',
            'capabilities_image'           => 'Capabilities Image',
            'online_course_1_title'        => 'Online Course Block 1 Title',
            'online_course_1_content'      => 'Online Course Block 1 Content',
            'online_course_1_image'        => 'Online Course Block 1 Image',
            'online_course_2_title'        => 'Online Course Block 2 Title',
            'online_course_2_content'      => 'Online Course Block 2 Content',
            'online_course_2_image'        => 'Online Course Block 2 Image',
        ],
        'about_us_block' => [
            'title'   => 'Title',
            'content' => 'Content',
            'image'   => 'Image',
        ],
    ],
];
