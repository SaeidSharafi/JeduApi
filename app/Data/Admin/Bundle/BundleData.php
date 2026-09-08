<?php

declare(strict_types=1);

namespace App\Data\Admin\Bundle;

use App\Contracts\ProductableDataContract;
use App\Enums\Content\PublicationStatusEnum;
use App\Models\Bundle;
use Spatie\LaravelData\Data;

final class BundleData extends Data implements ProductableDataContract
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $full_name,
        public string $short_name,
        public ?string $description,
        public ?string $thumbnail_url,
        public PublicationStatusEnum $status,
        public ?array $properties,
        public ?array $additional_info,
        public ?array $faq,
        public array $media = [],
    ) {}

    public static function fromModel(Bundle $bundle): self
    {
        return self::factory()->withoutMagicalCreation()->from($bundle->toArray());
    }
}
