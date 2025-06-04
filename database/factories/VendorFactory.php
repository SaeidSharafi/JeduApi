<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Plank\Mediable\Media;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->iranPhone(),
            'phone2' => $this->faker->optional()->iranPhone(),
            'address' => $this->faker->address(),
            'map_location' => $this->faker->url(),
            'logo_url' => $this->faker->imageUrl(),
            'favicon_url' => $this->faker->imageUrl(),
            'social_links' => [
                'facebook' => $this->faker->url(),
                'twitter' => $this->faker->url(),
                'instagram' => $this->faker->url(),
                'linkedin' => $this->faker->url(),
            ],
            'theme_options' => [
                'primary_color' => $this->faker->hexColor(),
                'secondary_color' => $this->faker->hexColor(),
                'font_family' => $this->faker->word(),
                'layout' => $this->faker->randomElement(['boxed', 'full-width']),
            ],
        ];
    }

    public function withMedia(): static
    {
        return $this->afterCreating(function (Vendor $vendor) {
            $media = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%placeholder%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $vendor->attachMedia($media, 'logo');
            $vendor->logo_url = $media->getUrl();

            $media = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%icon%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $vendor->attachMedia($media, 'favicon');
            $vendor->favicon_url = $media->getUrl();

            $vendor->save();
        });
    }
    public function withLogo(): static
    {
        return $this->afterCreating(function (Vendor $vendor) {
            $media = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%placeholder%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $vendor->attachMedia($media, 'logo');
            $vendor->logo_url = $media->getUrl();
            $vendor->save();
        });
    }

    public function withFavicon(): static
    {
        return $this->afterCreating(function (Vendor $vendor) {
            $media = Media::query()
                ->where('directory', 'fake-media')
                ->whereLike('filename', '%icon%')
                ->where('extension', 'svg')
                ->inRandomOrder()
                ->first();
            $vendor->attachMedia($media, 'favicon');
            $vendor->favicon_url = $media->getUrl();
            $vendor->save();
        });
    }
}
