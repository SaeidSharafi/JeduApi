<?php

declare(strict_types=1);

namespace App\Data\Admin\Category;

use App\Enums\System\MorphTypeEnum;
use App\Models\Categorizable;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

final class CategorizableListItemData extends Data
{
    public function __construct(
        public int $pivot_id,
        public int $category_id,
        public int $categorizable_id,
        public string $categorizable_type,
        public string $categorizable_name,
        public bool $good_for_start,
    ) {}

    public static function fromModel(Categorizable $model): self
    {
        return new self(
            $model->id,
            $model->category_id,
            $model->categorizable_id,
            MorphTypeEnum::from($model->categorizable_type)->translate(),
            self::getCategorizableName($model->categorizable),
            (bool) $model->good_for_start,
        );
    }

    /**
     * @codeCoverageIgnore
     */
    private static function getCategorizableName(Model $categorizable): string
    {
        if (isset($categorizable->name)) {
            return $categorizable->name;
        }
        if (isset($categorizable->title)) {
            return $categorizable->title;
        }
        if (isset($categorizable->short_name)) {
            return $categorizable->short_name;
        }
        if (isset($categorizable->full_name)) {
            return $categorizable->full_name;
        }

        return 'N/A';
    }
}
