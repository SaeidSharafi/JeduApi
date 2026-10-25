<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The IP addresses (or ranges) of the reverse proxies whose forwarding
    | headers Laravel may believe. Leave this null unless the application is
    | actually behind a proxy: the framework then trusts nobody, which is the
    | correct value whenever the connecting peer is the client itself.
    |
    | `*` is not "trust everything". Laravel reads it as "trust the immediate
    | peer", which is the right value for a container that only ever receives
    | traffic from the proxy in front of it (see
    | Illuminate\Http\Middleware\TrustProxies::setTrustedProxyIpAddressesToTheCallingIp).
    | A comma-separated list of addresses is also accepted.
    |
    | This is a deployment concern, not an application one: the installer in
    | deploy/production writes it for the Docker stack, and the bare-metal
    | deployment leaves it unset because nginx there connects to php-fpm
    | directly.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
