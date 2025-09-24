<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContactUsRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class ContactUsRequestFactory extends Factory
{
    protected $model = ContactUsRequest::class;

    public function definition(): array
    {
        return [
            'full_name'  => $this->faker->name(),
            'phone'      => $this->faker->mobile(),
            'subject'    => $this->faker->persianWords(2, true),
            'email'      => $this->faker->unique()->safeEmail(),
            'message'    => $this->faker->persianParagraph(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
