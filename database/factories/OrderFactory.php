<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
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
        $symbols = ['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'ADA', 'XRP', 'DOT', 'DOGE', 'MATIC'];
        $sides = [Order::SIDE_BUY, Order::SIDE_SELL];
        $statuses = [Order::STATUS_OPEN, Order::STATUS_FILLED, Order::STATUS_CANCELLED];

        return [
            'user_id' => User::factory(),
            'symbol' => fake()->randomElement($symbols),
            'side' => fake()->randomElement($sides),
            'price' => fake()->randomFloat(8, 100, 100000),
            'amount' => fake()->randomFloat(8, 0.001, 10),
            'status' => fake()->randomElement($statuses),
        ];
    }
}
