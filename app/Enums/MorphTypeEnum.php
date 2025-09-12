<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Category;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\HomePageBlock;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Seminar;
use App\Models\Slider;
use App\Models\Staff;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WalletCampaign;
use App\Traits\AdvanceEnum;

enum MorphTypeEnum: string
{
    use AdvanceEnum;

    case CATEGORY      = 'category';
    case COURSE        = 'course';
    case SEMINAR       = 'seminar';
    case DIGITAL_ASSET = 'digital_asset';
    case STAFF         = 'staff';
    case USER          = 'user';

    case TEACHER  = 'teacher';
    case VENDOR   = 'vendor';
    case PRODUCT  = 'product';
    case ORDER    = 'order';
    case REFUND   = 'refund';
    case CAMPAIGN = 'campaign';
    case SLIDER   = 'slider';
    case HOME_PAGE_BLOCK   = 'home_page_block';

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
            self::STAFF         => Staff::class,
            self::USER          => User::class,
            self::TEACHER       => Teacher::class,
            self::VENDOR        => Vendor::class,
            self::PRODUCT       => Product::class,
            self::ORDER         => Order::class,
            self::REFUND        => Refund::class,
            self::CAMPAIGN      => WalletCampaign::class,
            self::SLIDER       => Slider::class,
            self::HOME_PAGE_BLOCK => HomePageBlock::class,
        };
    }
}
