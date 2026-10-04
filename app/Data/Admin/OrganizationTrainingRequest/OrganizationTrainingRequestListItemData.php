<?php

declare(strict_types=1);

namespace App\Data\Admin\OrganizationTrainingRequest;

use App\Data\Admin\Auth\StaffData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\InboundRequestStatusEnum;
use App\Models\OrganizationTrainingRequest;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class OrganizationTrainingRequestListItemData extends Data
{
    /**
     * @param  array<int, string>  $requested_course_names
     * @param  array<string, int|string>|null  $vendor_snapshot
     */
    public function __construct(
        public int $id,
        public string $reference,
        public string $first_name,
        public string $last_name,
        public string $phone,
        public string $position,
        public string $organization_name,
        public array $requested_course_names,
        #[WithTransformer(TranslatableEnumData::class)] public InboundRequestStatusEnum $status,
        public ?StaffData $assignee,
        public bool $has_attachment,
        public ?array $vendor_snapshot,
        public ?Verta $created_at,
    ) {}

    public static function fromModel(OrganizationTrainingRequest $request): self
    {
        return new self(
            id: $request->id,
            reference: $request->uuid,
            first_name: $request->first_name,
            last_name: $request->last_name,
            phone: $request->phone,
            position: $request->position,
            organization_name: $request->organization_name,
            requested_course_names: $request->requested_course_names,
            status: $request->status,
            assignee: $request->assignee ? StaffData::from($request->assignee) : null,
            has_attachment: $request->hasMedia('attachment'),
            vendor_snapshot: $request->vendor_snapshot,
            created_at: $request->created_at ? Verta::instance($request->created_at) : null,
        );
    }
}
