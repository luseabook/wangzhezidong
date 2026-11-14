<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Master>
 */
class MasterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "username" => $this->faker->unique()->userName(),
            "password" => bcrypt("password"),
            "type" => 1,
            "realname" => $this->faker->name(),
            "account" => $this->faker->unique()->phoneNumber(),
            "parent_id" => 0,
            "parent_rate" => 0,
        ];
    }
}
