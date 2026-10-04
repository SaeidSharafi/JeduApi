<?php

declare(strict_types=1);

namespace App\Data\Admin\OrganizationTrainingRequest;

use Plank\Mediable\Media;
use Spatie\LaravelData\Data;

final class OrganizationTrainingRequestAttachmentData extends Data
{
    public function __construct(
        public int $id,
        public string $file_name,
        public int $size,
        public string $mime_type,
        public string $extension,
        public string $download_url,
    ) {}

    public static function fromModel(Media $media, int $requestId): self
    {
        return new self(
            id: $media->id,
            file_name: $media->filename,
            size: $media->size,
            mime_type: $media->mime_type,
            extension: $media->extension,
            download_url: route('api.v1.admin.organization-training-requests.attachment.download', [
                'organizationTrainingRequest' => $requestId,
            ]),
        );
    }
}
