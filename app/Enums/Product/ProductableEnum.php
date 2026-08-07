<?php

declare(strict_types=1);

namespace App\Enums\Product;

use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Seminar;
use App\Traits\AdvanceEnum;

enum ProductableEnum: string
{
    /** @use AdvanceEnum<value-of<self>> */
    use AdvanceEnum;
    case COURSE        = 'course';
    case SEMINAR       = 'seminar';
    case DIGITAL_ASSET = 'digital_asset';

    public static function getAlias(string $modelClass): ?string
    {
        foreach (self::cases() as $case) {
            if ($case->getModelClass() === $modelClass) {
                return $case->value;
            }
        }

        return null;
    }

    public static function getTableFromType(?string $type): string
    {
        return match ($type) {
            self::COURSE->value        => 'courses',
            self::SEMINAR->value       => 'seminars',
            self::DIGITAL_ASSET->value => 'digital_assets',
            default                    => 'courses',
        };
    }

    public function getModelClass(): string
    {
        return match ($this) {
            self::COURSE        => Course::class,
            self::SEMINAR       => Seminar::class,
            self::DIGITAL_ASSET => DigitalAsset::class,
        };
    }

    // allow more than 1 quantity
    public function allowsMultipleQuantity(): bool
    {
        return match ($this) {
            self::COURSE        => false,
            self::SEMINAR       => false,
            self::DIGITAL_ASSET => false,
        };
    }
}
