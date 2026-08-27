<?php

declare(strict_types=1);

namespace App\Data\Admin\ContactRequest;

use App\Data\Admin\Auth\StaffData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\InboundRequestStatusEnum;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class ContactRequestListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $full_name,
        public ?string $phone,
        public ?string $email,
        public string $subject,
        #[WithTransformer(TranslatableEnumData::class)]
        public InboundRequestStatusEnum $status,
        public ?StaffData $assignee,
        public ?string $created_at,
    ) {}
}
