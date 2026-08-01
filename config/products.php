<?php

declare(strict_types=1);

return [
    'availability' => [
        'use_denormalized'   => (bool) env('PRODUCT_AVAILABILITY_USE_DENORMALIZED', true),
        'capacity_threshold' => 0.8,
    ],
];
