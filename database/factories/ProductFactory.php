<?php

declare(strict_types=1);

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

final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(ProductableEnum::cases());

        switch ($type) {
            case ProductableEnum::COURSE:
                $productableType = ProductableEnum::COURSE->value;
                $productableId = Course::factory();
                break;
            case ProductableEnum::SEMINAR:
                $productableType = ProductableEnum::SEMINAR->value;
                $productableId = \App\Models\Seminar::factory();
                break;
            case ProductableEnum::DIGITAL_ASSET:
                $productableType = ProductableEnum::DIGITAL_ASSET->value;
                $productableId = \App\Models\DigitalAsset::factory();
                break;
            default:
                $productableType = ProductableEnum::COURSE->value;
                $productableId = Course::factory();
        }

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
            'productable_type'  => $productableType,
            'productable_id'    => $productableId,
        ];
    }

    public function withCategory(int $maxCategoryCount = 1): self
    {
        $categoryCount = rand(1, $maxCategoryCount);

        return $this->afterCreating(function (Product $product) use ($categoryCount) {
            if (Category::query()->count() < 10) {
                $product->categories()->attach(
                    Category::factory()->count($categoryCount)->create()
                );

                return;
            }

            $product->categories()->attach(
                Category::query()
                    ->inRandomOrder()
                    ->take($categoryCount)
                    ->get()
            );

        });
    }

    public function withDeliveryOptions(int $count = 3): static
    {
        return $this->afterCreating(function (Product $product) use ($count) {
            \App\Models\ProductDeliveryOption::factory()
                ->withTeachers()
                ->count($count)
                ->create([
                    'product_id' => $product->id,
                ]);
        });
    }
}
