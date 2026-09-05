<?php

declare(strict_types=1);

return [
    'bundles' => [
        'allow_repeated_productables' => (bool) env('BUNDLES_ALLOW_REPEATED_PRODUCTABLES', true),
        'max_components'              => (int) env('BUNDLES_MAX_COMPONENTS', 30),
    ],
    'availability' => [
        'use_denormalized'   => (bool) env('PRODUCT_AVAILABILITY_USE_DENORMALIZED', true),
        'capacity_threshold' => 0.8,
    ],
];
