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
        'terminal_id'      => env('MELLAT_TERMINAL_ID'),
        'username'         => env('MELLAT_USERNAME'),
        'password'         => env('MELLAT_PASSWORD'),
        'server_url'       => env('MELLAT_SERVER_URL', 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl'),
        'gateway_url'      => env('MELLAT_GATEWAY_URL', 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat'),
        'callback_url'     => env('MELLAT_CALLBACK_URL', env('APP_URL').'/api/v1/shop/payment/gateway/callback'),
        'test_mode'        => env('MELLAT_TEST_MODE', false),
        'test_server_url'  => env('MELLAT_TEST_SERVER_URL', 'https://sandbox.banktest.ir/mellat/bpm.shaparak.ir/pgwchannel/services/pgw?wsdl'),
        'test_gateway_url' => env('MELLAT_TEST_GATEWAY_URL', 'https://sandbox.banktest.ir/mellat/bpm.shaparak.ir/pgwchannel/startpay.mellat'),
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
];
