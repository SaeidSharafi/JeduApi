<?php

namespace App\Data\User;

use Spatie\LaravelData\Data;

class ShowUserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone = null,

    )
    {
    }
}
