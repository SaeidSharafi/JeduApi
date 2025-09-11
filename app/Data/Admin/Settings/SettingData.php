<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use Spatie\LaravelData\Data;

final class SettingData extends Data
{
    public function __construct(
        public int $id,
        public string $key,
        public mixed $value,
        public ?string $type = null,
        public ?string $group = null,
    ) {}
}
