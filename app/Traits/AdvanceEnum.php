<?php

declare(strict_types=1);

namespace App\Traits;

trait AdvanceEnum
{
    /**
     * Get all status values as an array.
     *
     * @return array<int,string>
     */
    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all status names (enum case names) as an array.
     *
     * @return array<int,string>
     */
    public static function getAllNames(): array
    {
        return array_column(self::cases(), 'name');
    }

    public function translate(): string
    {
        $key = class_basename($this);

        return __("enums.{$key}.{$this->value}");
    }

    /**
     * Get Key-Value Pairs for Enum
     *
     * @return array<string,string>
     */
    public static function getKeyValuePairs(): array
    {
        $keyValuePairs = [];
        foreach (self::cases() as $value) {
            $keyValuePairs[$value->value] = $value->translate();
        }

        return $keyValuePairs;
    }

    /**
     * get Value Label array
     *
     * @return array<int, array<string, string>>
     */
    public static function getValueLabel(): array
    {
        return array_map(
            fn ($case): array => [
                'value' => $case->value,
                'label' => $case->translate(),
            ],
            self::cases()
        );
    }
}
