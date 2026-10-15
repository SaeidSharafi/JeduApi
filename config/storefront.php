<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront Cache Revalidation
    |--------------------------------------------------------------------------
    |
    | Publishing content must clear the storefront's Next Data Cache before its
    | TTL expires. `revalidate_secret` must equal the storefront container's
    | REVALIDATE_SECRET, and a missing url or secret disables the webhook for
    | this environment (no request is sent, the failure is logged at debug).
    |
    | `url` has no default on purpose. This is a server-to-server call made from
    | inside a container, so it must be the address the API can reach the
    | storefront on — not the browser-facing host used by the payment redirects
    | (FRONTEND_SHOP_DOMAIN), which resolves to the API container itself and fails
    | with "could not connect to server". In local Docker that address is usually
    | http://host.docker.internal:<storefront port>, and the storefront must bind
    | beyond 127.0.0.1 for the container to reach it.
    |
    */

    'url' => env('STOREFRONT_URL'),

    'revalidate_secret' => env('STOREFRONT_REVALIDATE_SECRET'),

    'timeout' => (int) env('STOREFRONT_REVALIDATE_TIMEOUT', 3),

];
