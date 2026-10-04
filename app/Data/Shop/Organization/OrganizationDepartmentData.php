<?php

declare(strict_types=1);

namespace App\Data\Shop\Organization;

use App\Models\Vendor;
use Spatie\LaravelData\Data;

final class OrganizationDepartmentData extends Data
{
    public function __construct(
        public string $name,
    ) {}

    public static function fromModel(Vendor $vendor): self
    {
        return new self(
            name: $vendor->name,
        );
    }
}
