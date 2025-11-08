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
        'terminal_id'  => env('MELLAT_TERMINAL_ID'),
        'username'     => env('MELLAT_USERNAME'),
        'password'     => env('MELLAT_PASSWORD'),
        'server_url'   => env('MELLAT_SERVER_URL', 'https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl'),
        'gateway_url'  => env('MELLAT_GATEWAY_URL', 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat'),
        'callback_url' => env('MELLAT_CALLBACK_URL', env('APP_URL').'/api/v1/shop/payment/gateway/callback'),
    ],
];
