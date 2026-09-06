<?php

declare(strict_types=1);

namespace App\Enums\Product;

use App\Traits\AdvanceEnum;

enum BundleReviewReasonEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;

    case BASE_PRICE_CHANGED          = 'base_price_changed';
    case DELIVERY_METHOD_CHANGED     = 'delivery_method_changed';
    case PROVIDER_IDENTIFIER_CHANGED = 'provider_identifier_changed';
    case ACCESS_DURATION_CHANGED     = 'access_duration_changed';
    case PRODUCT_ASSOCIATION_CHANGED = 'product_association_changed';
    case TERM_CHANGED                = 'term_changed';
    case COMPONENT_ARCHIVED          = 'component_archived';
    case INVALID_COMPOSITION         = 'invalid_composition';
}
