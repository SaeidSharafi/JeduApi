<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| The browser-facing hostnames are environment data, not code: every
| deployment serves its own admin panel, storefront and API from different
| hosts. The defaults below are the hosted application's own origins, so an
| environment that sets none of the CORS_* variables behaves exactly as it
| did before these knobs existed.
|
| CORS_ALLOWED_ORIGINS and CORS_ALLOWED_ORIGINS_PATTERNS are comma-separated.
| An origin may use a `*` wildcard (it becomes a pattern); `https://*.jedu.ir`
| does not match `https://jedu.ir`, so both are listed. Setting
| CORS_ALLOWED_ORIGINS to an empty value denies every cross-origin browser
| request instead of falling back to the defaults.
|
*/

$parseOrigins = static function (?string $value): ?array {
    if ($value === null) {
        return null;
    }

    return array_values(array_filter(
        array_map(trim(...), explode(',', $value)),
        static fn (string $origin): bool => $origin !== '',
    ));
};

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $parseOrigins(env('CORS_ALLOWED_ORIGINS')) ?? [
        'http://localhost:3000',

        'https://*.jedu.ir',
        'https://jedu.ir',

        'https://dev-jedu.encel.ir',
        'https://dev-admin-jedu.encel.ir',
        'https://test-jedu.encel.ir',
        'https://test-admin-jedu.encel.ir',
    ],

    'allowed_origins_patterns' => $parseOrigins(env('CORS_ALLOWED_ORIGINS_PATTERNS')) ?? [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 0),

    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', true),

];
