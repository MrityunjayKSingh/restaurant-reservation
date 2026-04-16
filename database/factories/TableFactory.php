<?php

namespace Database\Factories;

use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

class TableFactory extends Factory
{
    protected $model = Table::class;

    private static int $tableNumberSequence = 1;

    public function definition(): array
    {
        return [
            'table_number' => self::$tableNumberSequence++,
            'capacity'     => $this->faker->randomElement([2, 2, 4, 4, 6, 8]),
            'location'     => $this->faker->randomElement(['indoor', 'outdoor']),
            'is_active'    => true,
            'notes'        => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function indoor(): static
    {
        return $this->state(['location' => 'indoor']);
    }

    public function outdoor(): static
    {
        return $this->state(['location' => 'outdoor']);
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(['capacity' => $capacity]);
    }
}
