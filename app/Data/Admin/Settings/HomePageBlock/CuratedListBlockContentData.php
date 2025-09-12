<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings\HomePageBlock;

use App\Enums\HomePageBlockTypeEnum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CuratedListBlockContentData extends Data
{
    public function __construct(
        public array $items,
        public string $preset = 'default',
    ) {}


    /**
     * @codeCoverageIgnore
     */
    public static function rules(?ValidationContext $context = null): array
    {
        $type = request()->input('type') ?? HomePageBlockTypeEnum::CURATED_LIST->value;
        $table = $type === HomePageBlockTypeEnum::MAIN_CATEGORIES->value ? 'categories' : 'products';
        return [
            'items'   => ['required', 'array'],
            'items.*' => ['integer', 'exists:'.$table.',id'],
            'preset'  => ['required', 'string'],
        ];
    }
}
