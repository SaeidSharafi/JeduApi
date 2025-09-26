<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\HomePageBlock;

use App\Enums\Content\DynamicListEntityTypeEnum;
use App\Enums\Content\DynamicListSortByEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class DynamicListBlockContentData extends Data
{
    public function __construct(
        public DynamicListEntityTypeEnum $entity_type,
        public DynamicListSortByEnum $sort_by,
        public int $limit,
        public string $preset = 'default',
        public ?array $category_ids = null,
    ) {}

    /**
     * @codeCoverageIgnore
     */
    public static function rules(?ValidationContext $context = null): array
    {
        return [
            'entity_type'    => ['required', 'string', Rule::enum(DynamicListEntityTypeEnum::class)],
            'sort_by'        => ['required', 'string', Rule::enum(DynamicListSortByEnum::class)],
            'limit'          => ['required', 'integer', 'min:1', 'max:20'],
            'preset'         => ['required', 'string'],
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
