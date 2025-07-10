<?php

declare(strict_types=1);

namespace App\Data\Admin\Role;

use Spatie\LaravelData\Data;

final class RoleListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $label,
    ) {}
}
