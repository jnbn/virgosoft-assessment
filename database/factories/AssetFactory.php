<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $symbols = ['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'ADA', 'XRP', 'DOT', 'DOGE', 'MATIC'];

        return [
            'user_id' => User::factory(),
            'symbol' => fake()->randomElement($symbols),
            'amount' => fake()->randomFloat(8, 0, 100),
            'locked_amount' => fake()->randomFloat(8, 0, 10),
        ];
    }
}
