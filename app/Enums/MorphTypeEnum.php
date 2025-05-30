<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Seminar;
use App\Models\User;
use App\Traits\AdvanceEnum;

enum MorphTypeEnum: string
{
    use AdvanceEnum;

    case CATEGORY      = 'category';
    case COURSE        = 'course';
    case SEMINAR       = 'seminar';
    case DIGITAL_ASSET = 'digital_asset';
    case ADMIN         = 'admin';
    case USER          = 'user';

    public static function forMorphMap(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->getModelClass();
        }

        return $map;
    }

    public static function getAlias(string $modelClass): ?string
    {
        foreach (self::cases() as $case) {
            if ($case->getModelClass() === $modelClass) {
                return $case->value;
            }
        }

        return null;
    }

    public function getModelClass(): string
    {
        return match ($this) {
            self::CATEGORY      => Category::class,
            self::COURSE        => Course::class,
            self::SEMINAR       => Seminar::class,
            self::DIGITAL_ASSET => DigitalAsset::class,
            self::ADMIN         => Admin::class,
            self::USER          => User::class,
        };
    }
}
