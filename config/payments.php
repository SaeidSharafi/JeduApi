<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for various payment gateways used in the application.
    |
    */

    'mellat' => [
        'terminal_id'             => env('MELLAT_TERMINAL_ID'),
        'username'                => env('MELLAT_USERNAME'),
        'password'                => env('MELLAT_PASSWORD'),
        'ims_bank_account_number' => env('MELLAT_IMS_BANK_ACCOUNT_NUMBER'),
        'server_url'              => env('MELLAT_SERVER_URL', 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl'),
        'gateway_url'             => env('MELLAT_GATEWAY_URL', 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat'),
        'callback_url'            => env('MELLAT_CALLBACK_URL', env('APP_URL').'/api/v1/shop/payment/gateway/callback'),
        'test_mode'               => env('MELLAT_TEST_MODE', false),
        'test_server_url'         => env('MELLAT_TEST_SERVER_URL', 'https://sandbox.banktest.ir/mellat/bpm.shaparak.ir/pgwchannel/services/pgw?wsdl'),
        'test_gateway_url'        => env('MELLAT_TEST_GATEWAY_URL', 'https://sandbox.banktest.ir/mellat/bpm.shaparak.ir/pgwchannel/startpay.mellat'),
    ],

    'bank_transfer' => [
        'ims_bank_account_number' => env('BANK_TRANSFER_IMS_BANK_ACCOUNT_NUMBER'),
    ],

    'wallet' => [
        'ims_bank_account_number' => env('WALLET_IMS_BANK_ACCOUNT_NUMBER'),
    ],

    'digipay' => [
        'allow_partial_refund' => env('DIGIPAY_ALLOW_PARTIAL_REFUND', false),
        'endpoints'            => [
            'production' => [
                'base_url' => 'https://api.mydigipay.com',
                'web_url'  => 'https://app.mydigipay.ir',
            ],
            'sandbox' => [
                'base_url' => 'https://uat.mydigipay.info',
                'web_url'  => 'https://uatweb.mydigipay.info',
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | API Paths
        |--------------------------------------------------------------------------
        */
        'paths' => [
            'oauth_token' => '/digipay/api/oauth/token',
            'ticket'      => '/digipay/api/tickets/business',
            'verify'      => '/digipay/api/purchases/verify',
            'reverse'     => '/digipay/api/reverse',
            'deliver'     => '/digipay/api/purchases/deliver',
            'refund'      => '/digipay/api/refunds',
        ],

        /*
        |--------------------------------------------------------------------------
        | Request Configuration
        |--------------------------------------------------------------------------
        */
        'timeout'     => env('DIGIPAY_TIMEOUT', 30),
        'retry_times' => env('DIGIPAY_RETRY_TIMES', 2),
        'retry_delay' => env('DIGIPAY_RETRY_DELAY', 500),

        /*
        |--------------------------------------------------------------------------
        | Default API Version
        |--------------------------------------------------------------------------
        */
        'default_api_version' => '2022-02-02',

        /*
        |--------------------------------------------------------------------------
        | Gateway Types (for preferredGateway)
        |--------------------------------------------------------------------------
        */
        'gateway_types' => [
            'wallet' => 0,
            'ipg'    => 2,
            'credit' => 5,
            'bnpl'   => 13,
        ],

        /*
        |--------------------------------------------------------------------------
        | Ticket Type for UPG
        |--------------------------------------------------------------------------
        */
        'ticket_type' => 11,

        /*
        |--------------------------------------------------------------------------
        | Logging Configuration
        |--------------------------------------------------------------------------
        */
        'logging' => [
            'enabled'          => env('DIGIPAY_LOGGING', true),
            'channel'          => env('DIGIPAY_LOG_CHANNEL', 'digipay'),
            'sensitive_fields' => ['client_secret', 'password', 'access_token', 'refresh_token'],
        ],

        /*
        |--------------------------------------------------------------------------
        | Token Cache Configuration
        |--------------------------------------------------------------------------
        */
        'token_cache' => [
            'key'    => 'digipay_access_token',
            'buffer' => 300, // Refresh token 5 minutes before expiry
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Transaction Reference Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for generating unique transaction references.
    | Transaction references are numeric-only sequential IDs.
    |
    */

    'transaction_reference' => [
        /*
         | Starting number for the first payment transaction
         | Starts at 200M to differentiate from order increment_ids (100M)
         */
        'start_from' => (int) env('PAYMENT_TRANSACTION_START', 200000001),
    ],

    'redirect' => [
        'success' => env('FRONTEND_PAYMENT_SUCCESS_URL', 'payment/success'),
        'failure' => env('FRONTEND_PAYMENT_FAILURE_URL', 'payment/fail'),
    ],
];
