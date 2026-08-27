<?php

declare(strict_types=1);

namespace App\Data\Admin\CollaborationRequest;

use App\Data\Admin\Auth\StaffData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\InboundRequestStatusEnum;
use App\Models\CollaborationRequest;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class CollaborationRequestListItemData extends Data
{
    public function __construct(public int $id, public string $full_name, public ?string $phone, public ?string $email, public ?string $department, #[WithTransformer(TranslatableEnumData::class)] public InboundRequestStatusEnum $status, public ?StaffData $assignee, public bool $has_attachment, public ?string $created_at) {}

    public static function fromModel(CollaborationRequest $request): self
    {
        return new self($request->id, $request->full_name, $request->phone, $request->email, $request->department, $request->status, $request->assignee ? StaffData::from($request->assignee) : null, $request->hasMedia('attachment'), $request->created_at?->toISOString());
    }
}
