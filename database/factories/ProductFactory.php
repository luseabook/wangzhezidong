<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "itemId" => $this->faker->randomNumber(8),
            "itemTitle" => $this->faker->sentence(3),
            "price" => $this->faker->randomElement([100, 200, 300, 400, 500]),
            'sellerOpenId' => $this->faker->randomNumber(8),
            'sellerNick'   => $this->faker->name,
            "status" => 1,
            "commissionRate" => 10,
        ];
    }
}
