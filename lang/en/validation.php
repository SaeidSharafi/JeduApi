<?php

declare(strict_types=1);

return [
    'invalid_jalali_date' => 'The :attribute is not a valid Jalali date.',

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
            'cannot_delete_delivery_option_with_orders' => 'Cannot delete a delivery option that has enrollments or order history.',
        ],
        'product' => [
            'cannot_delete_product_with_orders' => 'Cannot delete a product that has order history.',
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

        // ── Order Create ─────────────────────────────────────────────────
        'order' => [
            'items' => [
                'product_delivery_option_id' => [
                    'required' => 'The product field is required for each order item.',
                    'exists'   => 'The selected product for the order item is invalid.',
                ],
                'payment_type' => [
                    'required' => 'The payment type field is required for each order item.',
                    'enum'     => 'The selected payment type for the order item is invalid.',
                ],
                'qty_ordered' => [
                    'integer' => 'The quantity for each order item must be an integer.',
                    'min'     => 'The quantity for each order item must be at least 1.',
                ],
            ],
        ],

        // ── Footer ──────────────────────────────────────────────────────
        'footer' => [
            'categories' => [
                'integer' => 'Each category item must be a valid ID.',
                'exists'  => 'The selected category does not exist.',
            ],
            'social_media_links' => [
                'platform' => [
                    'required' => 'The platform field is required for each social media link.',
                ],
                'link' => [
                    'required' => 'The link field is required for each social media link.',
                ],
            ],
            'certifications' => [
                'name' => [
                    'required' => 'The certification name is required for each item.',
                ],
                'image' => [
                    'integer' => 'The certification image must be a valid media ID.',
                    'exists'  => 'The selected certification image is invalid.',
                ],
                'html' => [
                    'string' => 'The certification HTML must be a string.',
                ],
            ],
        ],

        // ── Contact Info ─────────────────────────────────────────────────
        'contact_info' => [
            'addresses' => [
                'name' => [
                    'required' => 'The location name is required for each address.',
                ],
                'address' => [
                    'required' => 'The address field is required for each item.',
                ],
                'location_url' => [
                    'required' => 'The location URL is required for each address.',
                    'url'      => 'The location URL must be a valid URL.',
                ],
                'phone' => [
                    'required' => 'The phone field is required for each address.',
                ],
            ],
            'social_media_links' => [
                'platform' => [
                    'required' => 'The platform field is required for each social media link.',
                ],
                'link' => [
                    'required' => 'The link field is required for each social media link.',
                    'url'      => 'The social media link must be a valid URL.',
                ],
            ],
        ],

        // ── Teacher ──────────────────────────────────────────────────────
        'teacher' => [
            'social_links' => [
                'platform' => [
                    'required' => 'The platform field is required for each social link.',
                ],
                'link' => [
                    'required' => 'The link field is required for each social link.',
                    'url'      => 'The social link must be a valid URL.',
                ],
            ],
        ],

        // ── Discount Promotion ──────────────────────────────────────────
        'discount' => [
            'rules' => [
                'type' => [
                    'required' => 'The type field is required for each discount rule.',
                    'in'       => 'The discount rule type must be condition or action.',
                ],
                'handler' => [
                    'required' => 'The handler field is required for each discount rule.',
                ],
                'configuration' => [
                    'required' => 'The configuration field is required for each discount rule.',
                    'array'    => 'The discount rule configuration must be an array.',
                ],
            ],
            'coupons' => [
                'code' => [
                    'required' => 'The code field is required for each coupon.',
                ],
                'usage_limit' => [
                    'integer' => 'The coupon usage limit must be an integer.',
                    'min'     => 'The coupon usage limit must be at least 1.',
                ],
            ],
        ],

        // ── Course FAQ ───────────────────────────────────────────────────
        'course' => [
            'faq' => [
                'question' => [
                    'required' => 'The question field is required for each FAQ item.',
                ],
                'answer' => [
                    'required' => 'The answer field is required for each FAQ item.',
                ],
            ],
        ],

        // ── Seminar FAQ ──────────────────────────────────────────────────
        'seminar' => [
            'faq' => [
                'question' => [
                    'required' => 'The question field is required for each FAQ item.',
                ],
                'answer' => [
                    'required' => 'The answer field is required for each FAQ item.',
                ],
            ],
        ],

        // ── Digital Asset FAQ ────────────────────────────────────────────
        'digital_asset' => [
            'faq' => [
                'question' => [
                    'required' => 'The question field is required for each FAQ item.',
                ],
                'answer' => [
                    'required' => 'The answer field is required for each FAQ item.',
                ],
            ],
        ],

        // ── Blog Post ────────────────────────────────────────────────────
        'blog_post' => [
            'related_productables' => [
                'id' => [
                    'required' => 'The ID field is required for each related productable.',
                    'integer'  => 'The related productable ID must be an integer.',
                ],
                'type' => [
                    'required' => 'The type field is required for each related productable.',
                ],
            ],
            'media' => [
                'cover' => [
                    'integer' => 'Each cover item must be a valid media ID.',
                    'exists'  => 'The selected cover image is invalid.',
                ],
                'gallery' => [
                    'integer' => 'Each gallery item must be a valid media ID.',
                    'exists'  => 'The selected gallery image is invalid.',
                ],
                'video' => [
                    'integer' => 'Each video item must be a valid media ID.',
                    'exists'  => 'The selected video is invalid.',
                ],
            ],
        ],
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
