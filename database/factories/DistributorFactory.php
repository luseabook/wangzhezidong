<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Distributor>
 */
class DistributorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "type" => 1,
            "distributorId" => $this->faker->uuid,
            "distributorName" => $this->faker->name,
            "master_id" => $this->faker->randomNumber(6),
            "status" => 1,
        ];
    }
}
