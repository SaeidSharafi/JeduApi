<?php

namespace App\Data\Admin;

use App\Data\Role\RoleListItemData;
use App\Rules\IranMobilePhoneRule;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class ShowAdminData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $phone,
        public ?bool $is_admin = null,
        #[DataCollectionOf(RoleListItemData::class)]
        public ?DataCollection $roles,
    ) {
    }
}
