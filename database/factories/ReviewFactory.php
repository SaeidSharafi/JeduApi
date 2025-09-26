<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Content\ReviewStatusEnum;
use App\Enums\Product\ProductableEnum;
use App\Models\Course;
use App\Models\DigitalAsset;
use App\Models\Review;
use App\Models\Seminar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(ProductableEnum::cases());

        switch ($type) {
            case ProductableEnum::COURSE:
                $reviewableType = ProductableEnum::COURSE->value;
                $reviewableId   = Course::factory();
                break;
            case ProductableEnum::SEMINAR:
                $reviewableType = ProductableEnum::SEMINAR->value;
                $reviewableId   = Seminar::factory();
                break;
            case ProductableEnum::DIGITAL_ASSET:
                $reviewableType = ProductableEnum::DIGITAL_ASSET->value;
                $reviewableId   = DigitalAsset::factory();
                break;
            default:
                $reviewableType = ProductableEnum::COURSE->value;
                $reviewableId   = Course::factory();
        }

        return [
            'user_id'         => User::factory(),
            'reviewable_type' => $reviewableType,
            'reviewable_id'   => $reviewableId,
            'rating'          => random_int(1, 5),
            'title'           => $this->faker->persianWord(),
            'comment'         => $this->faker->persianText(),
            'status'          => $this->faker->randomElement(ReviewStatusEnum::cases()),
            'is_featured'     => $this->faker->boolean(),
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ];
    }
}
