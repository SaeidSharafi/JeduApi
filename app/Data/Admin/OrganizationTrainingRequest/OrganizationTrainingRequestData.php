<?php

declare(strict_types=1);

namespace App\Data\Admin\OrganizationTrainingRequest;

use App\Data\Admin\Auth\StaffData;
use App\Data\Admin\PrivateFileData;
use App\Data\Transformer\TranslatableEnumData;
use App\Enums\InboundRequestStatusEnum;
use App\Models\OrganizationTrainingRequest;
use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

final class OrganizationTrainingRequestData extends Data
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
        public ?string $notes,
        #[WithTransformer(TranslatableEnumData::class)] public InboundRequestStatusEnum $status,
        public ?StaffData $assignee,
        public ?array $vendor_snapshot,
        public ?PrivateFileData $attachment,
        public ?Verta $created_at,
        public ?Verta $updated_at,
    ) {}

    public static function fromModel(OrganizationTrainingRequest $request): self
    {
        $attachment = $request->firstMedia('attachment');

        return new self(
            id: $request->id,
            reference: $request->uuid,
            first_name: $request->first_name,
            last_name: $request->last_name,
            phone: $request->phone,
            position: $request->position,
            organization_name: $request->organization_name,
            requested_course_names: $request->requested_course_names,
            notes: $request->notes,
            status: $request->status,
            assignee: $request->assignee ? StaffData::from($request->assignee) : null,
            vendor_snapshot: $request->vendor_snapshot,
            attachment: $attachment ? PrivateFileData::fromModel(
                $attachment,
                'attachment',
                route('api.v1.admin.organization-training-requests.attachment.download', [
                    'organizationTrainingRequest' => $request,
                ]),
            ) : null,
            created_at: $request->created_at ? Verta::instance($request->created_at) : null,
            updated_at: $request->updated_at ? Verta::instance($request->updated_at) : null,
        );
    }
}
