<?php

namespace App\Data\Admin\AdviceRequest;

use App\Data\Admin\Auth\StaffData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\AdviceRequestStatusEnum;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

class AdviceRequestData extends Data
{
    public function __construct(
        public int $id,
        public ?string $phone,
        #[WithTransformer(TranslatableEnumData::class)]
        public AdviceRequestStatusEnum $status,
        public ?string $note,
        public ?StaffData $handler = null,
        public ?string $created_at,
        public ?string $updated_at,
    )
    {
    }
}
