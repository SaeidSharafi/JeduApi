<?php

declare(strict_types=1);

namespace App\Enums\Content;

use App\Traits\AdvanceEnum;

enum DynamicListEntityTypeEnum: string
{
    use AdvanceEnum;

    case COURSE_PRODUCTS        = 'course_products';        // Products where productable_type = Course
    case SEMINAR_PRODUCTS       = 'seminar_products';      // Products where productable_type = Seminar
    case DIGITAL_ASSET_PRODUCTS = 'digital_asset_products'; // Products where productable_type = DigitalAsset
    case BLOG_POST              = 'blog_post';                    // Actual blog posts (not products)
    case ALL_PRODUCTS           = 'all_products';              // All products regardless of productable_type
}
