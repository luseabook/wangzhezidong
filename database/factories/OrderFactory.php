<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $time = Carbon::now()->subMonth();
        $price = $this->faker->randomElement([100, 200, 300, 400, 500]);

        return [
            'oid'   => $this->faker->unique()->randomNumber(8),
            'createTime'   => (clone $time),
            'updateTime'   => (clone $time),
            'payTime'      => (clone $time)->addMinutes(10),
            'status'       => 70,
            'totalFee'     => $price,
            'expressFee'   => 0,
            'discountFee'     => 0,
            'num' => 1,
            'price' => $price,
            'refundStatus' => 0,
            'itemId' => $this->faker->unique()->randomNumber(6),
            'distributorId' => $this->faker->uuid,
            'distributorName' => $this->faker->name,
            'estimatedIncome' => 0,
            'commissionRate' => 1,
            'sendTime' => (clone $time)->addDay(),
            'sellerOpenId' => $this->faker->uuid,
            'sellerNick'   => $this->faker->name,
            'orderDetail' => json_encode([]),
            'type' => 1,
            'importStatus' => 0,
        ];
    }
}
