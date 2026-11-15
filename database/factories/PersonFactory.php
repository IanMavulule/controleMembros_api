<?php

namespace Database\Factories;

use App\Enums\GenderEnum;
use App\Enums\PersonTypeEnum;
use App\Models\ClassGroup;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'gender' => fake()->randomElement(GenderEnum::values()),
            'phone' => '+258'.fake()->unique()->numerify('8########'),
            'location_id' => null,
            'class_group_id' => ClassGroup::factory(),
            'type' => PersonTypeEnum::Visitor->value,
            'became_member_at' => null,
            'created_by' => null,
        ];
    }

    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PersonTypeEnum::Member->value,
            'became_member_at' => now(),
        ]);
    }
}
