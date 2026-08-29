<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed'   => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'permission' => [
        'resource' => [
            'advice_requests'          => 'Advice Requests',
            'audits'                   => 'Audits',
            'blog_categories'          => 'Blog Categories',
            'blog_posts'               => 'Blog Posts',
            'categories'               => 'Categories',
            'collaboration_requests'   => 'Collaboration Requests',
            'contact_us_requests'      => 'Contact Us Requests',
            'courses'                  => 'Courses',
            'discounts'                => 'Discounts',
            'enrollments'              => 'Enrollments',
            'files'                    => 'Files',
            'home_page_blocks'         => 'Home Page Blocks',
            'orders'                   => 'Orders',
            'partners'                 => 'Partners',
            'payments'                 => 'Payments',
            'product_delivery_options' => 'Product Delivery Options',
            'products'                 => 'Products',
            'refunds'                  => 'Refunds',
            'reviews'                  => 'Reviews',
            'roles'                    => 'Roles',
            'seminars'                 => 'Seminars',
            'settings'                 => 'Settings',
            'sliders'                  => 'Sliders',
            'student_stories'          => 'Student Stories',
            'teachers'                 => 'Teachers',
            'terms'                    => 'Terms',
            'users'                    => 'Users',
            'vendors'                  => 'Vendors',
            'wallet_campaigns'         => 'Wallet Campaigns',
            'wallets'                  => 'Wallets',
            'course'                   => 'Courses',
            'seminar'                  => 'Seminars',
            'digital_asset'            => 'Files',
            'user'                     => 'Users',
            'role'                     => 'Roles',
            'media'                    => 'Media',
            'file'                     => 'Private Files',
            'category'                 => 'Categories',
            'staff'                    => 'Staff',
            'custom_permission'        => 'Custom Permissions',
        ],
        'action' => [
            'view_any'     => 'View Any',
            'view'         => 'View',
            'view_scoped'  => 'View Scoped',
            'view_own'     => 'View Own',
            'create'       => 'Create',
            'update'       => 'Update',
            'update_own'   => 'Update Own',
            'delete'       => 'Delete',
            'delete_own'   => 'Delete Own',
            'restore'      => 'Restore',
            'force_delete' => 'Force Delete',
        ],
        'custom' => [
            'audits' => [
                'admin_actions_view'       => 'View Admin Actions',
                'compliance_reports_view'  => 'View Compliance Reports',
                'suspicious_activity_view' => 'View Suspicious Activity',
            ],
            'blog_posts' => [
                'publish' => 'Publish',
                'feature' => 'Feature',
            ],
            'enrollments' => [
                'diagnostics_view' => 'View Diagnostics',
                'retry_provision'  => 'Retry Provisioning',
                'waive_provision'  => 'Waive Provisioning',
            ],
            'orders' => [
                'approve' => 'Approve',
            ],
            'refunds' => [
                'skip_gateway'  => 'Skip Gateway',
                'update_status' => 'Update Status',
            ],
            'reviews' => [
                'update_featured_status' => 'Update Featured Status',
            ],
            'settings' => [
                'payment_update' => 'Update Payment Settings',
                'payment_view'   => 'View Payment Settings',
            ],
            'staff' => [
                'ban'          => 'Ban Staff',
                'manage_roles' => 'Manage Roles',
                'impersonate'  => 'Impersonate User',
            ],
            'users' => [
                'ban' => 'Ban User',
            ],
            'wallet_campaigns' => [
                'allocate'      => 'Allocate Campaign',
                'process_bonus' => 'Process Bonus',
            ],
            'wallets' => [
                'adjustment' => 'Adjust Balance',
                'deposit'    => 'Deposit',
                'withdrawal' => 'Withdraw',
            ],
        ],
        'custom_permission' => [
            'access_admin_panel' => 'Access Admin Panel',
        ],
    ],

];
