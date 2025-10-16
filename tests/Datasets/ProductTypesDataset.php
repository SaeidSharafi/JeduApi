<?php

declare(strict_types=1);

/**
 * Product types dataset for testing multiple product types with the same API structure.
 * Each entry is an array with [factory_method, route_prefix] for the product type.
 */
dataset('product_types', [
    'course'  => ['withCourse', 'courses'],
    'seminar' => ['withSeminar', 'seminars'],
     'digital_asset' => ['withDigitalAsset', 'digital-assets'],
]);
