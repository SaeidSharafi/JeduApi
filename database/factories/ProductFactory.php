<?php

namespace Database\Factories;

use App\Enums\ProductableEnum;
use App\Enums\PublicationStatusEnum;
use App\Models\Category;
use App\Models\Course;
use App\Models\Product;
use App\Models\Term;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'vendor_id'         => Vendor::factory(),
            'term_id'           => Term::factory(),
            'status'            => $this->faker->randomElement(PublicationStatusEnum::getAllValues()),
            'is_visible'        => $this->faker->boolean(),
            'short_description' => $this->faker->text(),
            'short_name'        => $this->faker->name(),
            'name'              => $this->faker->name(),
            'is_featured'       => $this->faker->boolean(),
            'details_json'      => [],
            'created_at'        => Carbon::now(),
            'updated_at'        => Carbon::now(),
        ];
    }

    public function configure(): static
    {
        $type = $this->faker->randomElement(ProductableEnum::cases());
        return match ($type) {
            ProductableEnum::COURSE => $this->state([
                'productable_type' => $type->value,
                'productable_id'   => \App\Models\Course::factory(),
            ]),
            ProductableEnum::SEMINAR => $this->state([
                'productable_type' => $type->value,
                'productable_id'   => \App\Models\Seminar::factory(),
            ]),
            ProductableEnum::DIGITAL_ASSET => $this->state([
                'productable_type' => $type->value,
                'productable_id'   => \App\Models\DigitalAsset::factory(),
            ]),
            default => $this,
        };
    }

    public function withCategory(int $categoryCount = 1): self
    {
        return $this->afterCreating(function (Course $course) use ($categoryCount) {
            if (Category::query()->count() < 10) {
                $course->categories()->attach(
                    Category::factory()->count($categoryCount)->create()
                );

                return;
            }

            $course->categories()->attach(
                Category::query()
                    ->inRandomOrder()
                    ->take($categoryCount)
                    ->get()
            );

        });
    }
}
