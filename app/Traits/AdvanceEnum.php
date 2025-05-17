<?php

namespace App\Traits;

trait AdvanceEnum
{
    public function translate(): string
    {
        $key =  class_basename($this);
        return __("enums.{$key}.{$this->value}");
    }

    /**
     * Get all status values as an array.
     *
     * @return array<string>
     */
    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all status names (enum case names) as an array.
     *
     * @return array<string>
     */
    public static function getAllNames(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Get Key-Value Pairs for Enum
     *
     * @return array<string>
     */
    public function getKeyValuePairs(): array
    {
        $keyValuePairs = [];
        foreach (self::cases() as $value) {
            $keyValuePairs[$value->value] = $value->traslate();
        }
        return $keyValuePairs;
    }

    /**
     * get Value Label array
     *
     * @return array<string>
     */
    public function getValueLabel(): array
    {
        return array_map(
            fn($case): array => [
                'value' => $case->value,
                'label' => $case->traslate(),
            ],
            self::cases()
        );
    }
}
