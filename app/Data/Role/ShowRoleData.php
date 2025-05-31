<?php

namespace App\Data\Role;

use Spatie\LaravelData\Data;

class ShowRoleData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $label,
    )
    {
    }
}
