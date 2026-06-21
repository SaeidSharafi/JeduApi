<?php

declare(strict_types=1);

namespace App\Data\Shop\Payment;

use Spatie\LaravelData\Data;

final class GatewayData extends Data
{
    public function __construct(
        public bool $enabled,
        public bool $shop_enabled,
        public string $label,
        public ?string $description,
        public ?string $icon_url,
    ) {}

    /**
     * Centralized common fields schema definition.
     */
}
