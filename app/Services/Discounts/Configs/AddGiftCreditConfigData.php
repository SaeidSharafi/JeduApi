<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddGiftCreditConfigData extends Data
{
    public function __construct(
        public int $amount,              // Gift credit amount in rials
        public bool $per_item = false,  // Award per item vs fixed amount
        public ?int $expires_days = null, // Optional expiration in days
        public ?string $description = null
    ) {}
}
