<?php

declare(strict_types=1);

namespace App\Data\Admin\CollaborationRequest;

use App\Data\Admin\Auth\StaffData;
use App\Data\Admin\PrivateFileData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\InboundRequestStatusEnum;
use App\Models\CollaborationRequest;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class CollaborationRequestData extends Data
{
    public function __construct(public int $id, public string $full_name, public ?string $phone, public ?string $email, public ?string $department, public string $message, #[WithTransformer(TranslatableEnumData::class)] public InboundRequestStatusEnum $status, public ?string $note, public ?StaffData $assignee, public ?PrivateFileData $attachment, public ?string $created_at, public ?string $updated_at) {}

    public static function fromModel(CollaborationRequest $request): self
    {
        $attachment = $request->firstMedia('attachment');

        return new self($request->id, $request->full_name, $request->phone, $request->email, $request->department, $request->message, $request->status, $request->note, $request->assignee ? StaffData::from($request->assignee) : null, $attachment ? PrivateFileData::fromModel($attachment, 'attachment') : null, $request->created_at?->toISOString(), $request->updated_at?->toISOString());
    }
}
