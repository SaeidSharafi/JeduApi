<?php

declare(strict_types=1);

namespace App\Enums\Product;

enum BundleUnavailableReasonEnum: string
{
    case VERSION_CHANGED   = 'version_changed';
    case UNAVAILABLE       = 'unavailable';
    case CAPACITY_EXCEEDED = 'capacity_exceeded';
}
