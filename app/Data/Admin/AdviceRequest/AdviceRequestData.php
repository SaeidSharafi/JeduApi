<?php

declare(strict_types=1);

namespace App\Data\Admin\AdviceRequest;

use App\Data\Admin\Auth\StaffData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\InboundRequestStatusEnum;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class AdviceRequestData extends Data
{
    public function __construct(
        public int $id,
        public ?string $phone,
        #[WithTransformer(TranslatableEnumData::class)]
        public InboundRequestStatusEnum $status,
        public ?string $note,
        public ?StaffData $handler,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}
}
