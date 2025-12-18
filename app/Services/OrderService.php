<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Jobs\MatchOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    /**
     * Place a new limit order.
     *
     * @param User $user
     * @param array $data
     * @return Order
     * @throws Exception
     */
    public function placeOrder(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            // Lock user for update to prevent race conditions on balance
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            $symbol = $data['symbol'];
            $side = $data['side'];
            $price = (string) $data['price'];
            $amount = (string) $data['amount'];

            // Use BCMath for strict financial calculations
            if ($side === Order::SIDE_BUY) {
                // Required USD = price * amount
                $requiredUsd = bcmul($price, $amount, 8);
                
                // Compare balances as strings
                if (bccomp((string)$user->balance, $requiredUsd, 8) === -1) {
                    throw new Exception('Insufficient USD balance');
                }

                // Deduct balance
                $user->balance = bcsub((string)$user->balance, $requiredUsd, 8);
                $user->save();
            } else {
                // For sell orders, check and lock assets
                $asset = $user->assets()
                    ->where('symbol', $symbol)
                    ->lockForUpdate()
                    ->first();

                if (!$asset) {
                    throw new Exception('Insufficient asset balance');
                }

                $available = bcsub((string)$asset->amount, (string)$asset->locked_amount, 8);
                
                if (bccomp($available, $amount, 8) === -1) {
                    throw new Exception('Insufficient asset balance');
                }

                // Lock assets
                $asset->locked_amount = bcadd((string)$asset->locked_amount, $amount, 8);
                $asset->save();
            }

            // Create Order
            $order = $user->orders()->create([
                'symbol' => $symbol,
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'status' => Order::STATUS_OPEN,
            ]);

            // Broadcast new order to orderbook channel (runs immediately after commit)
            DB::afterCommit(fn () => event(new OrderPlaced($order)));

            // Dispatch matching job after transaction commit
            MatchOrders::dispatch($order)->afterCommit();

            // Alternative: Call orders:match command directly via Artisan facade after transaction commits
            // This provides immediate synchronous matching instead of queued async processing
            // Uncomment the line below and comment out the MatchOrders::dispatch line above to use this approach
            // DB::afterCommit(fn () => Artisan::call('orders:match'));

            return $order;
        });
    }

    /**
     * Cancel an order.
     *
     * @param User $user
     * @param Order $order
     * @return Order
     * @throws Exception
     */
    public function cancelOrder(User $user, Order $order): Order
    {
        return DB::transaction(function () use ($user, $order) {
            // Re-fetch order with lock
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($order->user_id !== $user->id) {
                throw new Exception('Unauthorized');
            }

            if ($order->status !== Order::STATUS_OPEN) {
                throw new Exception('Order cannot be cancelled');
            }

            // Lock user/assets for refund
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            if ($order->side === Order::SIDE_BUY) {
                // Refund USD
                $lockedUsd = bcmul((string)$order->price, (string)$order->amount, 8);
                $user->balance = bcadd((string)$user->balance, $lockedUsd, 8);
                $user->save();
            } else {
                // Release asset lock
                $asset = $user->assets()
                    ->where('symbol', $order->symbol)
                    ->lockForUpdate()
                    ->first();
                
                if ($asset) {
                    $asset->locked_amount = bcsub((string)$asset->locked_amount, (string)$order->amount, 8);
                    // Prevent negative for safety
                    if (bccomp((string)$asset->locked_amount, '0', 8) === -1) {
                        $asset->locked_amount = '0';
                    }
                    $asset->save();
                }
            }

            $order->status = Order::STATUS_CANCELLED;
            $order->save();

            // Broadcast cancellation to orderbook channel
            DB::afterCommit(fn () => event(new OrderCancelled($order)));

            return $order;
        });
    }
}
