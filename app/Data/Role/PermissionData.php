<?php

namespace App\Data\Role;

use Spatie\LaravelData\Data;

class PermissionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    )
    {
    }
}
