<?php

namespace App\Data\Admin;

use App\Data\Role\RoleListItemData;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class AdminListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?Verta $created_at = null,
        public ?Verta $updated_at = null,
        public ?bool $is_admin = null,
        #[DataCollectionOf(RoleListItemData::class)]
        public ?DataCollection $roles,
    )
    {
    }
}
