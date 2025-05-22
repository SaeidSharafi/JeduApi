<?php

namespace App\Data\Auth;

use Spatie\LaravelData\Data;

class AdminData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $phone,
        public string $email,
    )
    {
    }
}
