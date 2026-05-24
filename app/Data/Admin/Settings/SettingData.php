<?php

declare(strict_types=1);

namespace App\Data\Admin\Settings;

use App\Models\Setting;
use App\Services\SettingSecretRedactor;
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

    public static function fromModel(Setting $setting): self
    {
        $redactor = new SettingSecretRedactor();

        return new self(
            id: $setting->id,
            key: $setting->key,
            value: $redactor->redact($setting->key, $setting->value),
            type: $setting->type,
            group: $setting->group,
        );
    }
}
