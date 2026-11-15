<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\Worship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worship>
 */
class WorshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'name' => fake()->randomElement(['Culto de Domingo', 'Culto de Oração', 'Vigília', 'Culto de Jovens']),
            'title' => fake()->optional()->sentence(4),
            'date' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', fake()->numberBetween(6, 18)),
            'end_time' => null,
            'created_by' => null,
        ];
    }

    /**
     * Culto já terminado: a hora de fim só é conhecida depois do início.
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_time' => sprintf('%02d:00', (int) substr($attributes['start_time'], 0, 2) + 2),
        ]);
    }
}
