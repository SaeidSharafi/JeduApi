<?php

declare(strict_types=1);

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class AddWalletCreditConfigData extends Data
{
    public function __construct(
        public int $amount,              // Credit amount in rials
        public bool $per_item = false,  // Award per item vs fixed amount
        public ?string $description = null
    ) {}
}
