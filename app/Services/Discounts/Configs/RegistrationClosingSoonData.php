<?php

namespace App\Services\Discounts\Configs;

use Spatie\LaravelData\Data;

class RegistrationClosingSoonData extends Data
{
    public function __construct(
        public int $days
    ) {}
}
