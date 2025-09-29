<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TermStatusEnum;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

final class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        $year      = (int) $this->faker->year();
        $startDate = $this->faker->dateTimeBetween(
            startDate: "$year-01-01",
            endDate: "$year-12-31"
        );

        return [
            'name'          => $this->faker->randomElement(['Fall', 'Spring', 'Summer']).' '.$this->faker->year(),
            'status'        => TermStatusEnum::ACTIVE,
            'academic_year' => $year.'-'.($year + 1),
            'start_date'    => $startDate->format('Y-m-d'),
            'end_date'      => Carbon::parse($startDate->format('Y-m-d'))->addMonths(3)->format('Y-m-d'),
        ];
    }
}
