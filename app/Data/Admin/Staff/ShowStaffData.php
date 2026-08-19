<?php

declare(strict_types=1);

namespace App\Data\Admin\Staff;

use App\Contracts\ProductableDataContract;
use App\Contracts\WalletTransactionSourceableDataContract;
use App\Data\Admin\Role\RoleListItemData;
use App\Data\Casts\AdvancedDateTimeInterfaceCast;
use App\Data\Transformer\AdvancedDateTimeInterfaceTransformer;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class ShowStaffData extends Data implements ProductableDataContract, WalletTransactionSourceableDataContract
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $phone,
        public ?bool $is_admin,
        #[DataCollectionOf(RoleListItemData::class)]
        public ?DataCollection $roles,
        public bool $is_banned = false,
        #[WithCast(AdvancedDateTimeInterfaceCast::class), WithTransformer(AdvancedDateTimeInterfaceTransformer::class)]
        public ?Verta $banned_at = null,
    ) {}
}
