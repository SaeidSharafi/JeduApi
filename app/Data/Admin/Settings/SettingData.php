<?php

namespace App\Data\Admin\Settings;

use Hekmatinasser\Verta\Verta;
use Spatie\LaravelData\Data;

class SettingData extends Data
{
    public function __construct(
        public int $id,
        public string $key,
        public mixed $value,
        public ?string $type = null,
        public ?string $group = null,
    )
    {
    }
}
