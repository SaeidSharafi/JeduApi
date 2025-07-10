<?php

declare(strict_types=1);

namespace App\Data\Admin\Staff;

use App\Data\Admin\Role\RoleListItemData;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class StaffListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?Verta $created_at,
        public ?Verta $updated_at,
        public ?bool $is_admin,
        #[DataCollectionOf(RoleListItemData::class)]
        public ?DataCollection $roles,
    ) {}
}
