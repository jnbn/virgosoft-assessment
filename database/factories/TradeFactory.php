<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trade>
 */
class TradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $symbols = ['BTC', 'ETH', 'USDT', 'BNB', 'SOL', 'ADA', 'XRP', 'DOT', 'DOGE', 'MATIC'];
        $symbol = fake()->randomElement($symbols);
        $price = fake()->randomFloat(8, 100, 100000);
        $amount = fake()->randomFloat(8, 0.001, 10);

        // Create buyer and seller users
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        // Create buy and sell orders
        $buyOrder = Order::factory()->create([
            'user_id' => $buyer->id,
            'symbol' => $symbol,
            'side' => Order::SIDE_BUY,
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_FILLED,
        ]);

        $sellOrder = Order::factory()->create([
            'user_id' => $seller->id,
            'symbol' => $symbol,
            'side' => Order::SIDE_SELL,
            'price' => $price,
            'amount' => $amount,
            'status' => Order::STATUS_FILLED,
        ]);

        return [
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'symbol' => $symbol,
            'price' => $price,
            'amount' => $amount,
        ];
    }
}
