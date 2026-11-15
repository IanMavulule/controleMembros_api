<?php

namespace Database\Factories;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_code' => RoleEnum::Worker->codePrefix().'-'.strtoupper(Str::random(6)),
            'role' => RoleEnum::Worker->value,
            'name' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'phone' => '+258'.fake()->unique()->numerify('84#######'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_code' => RoleEnum::Admin->codePrefix().'-'.strtoupper(Str::random(6)),
            'role' => RoleEnum::Admin->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
