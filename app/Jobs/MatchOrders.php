<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchOrders implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?Order $newOrder = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->newOrder) {
            $this->matchOrder($this->newOrder);
        } else {
            // Process all open orders when no specific order is given
            $this->matchAllOpenOrders();
        }
    }

    /**
     * Attempt to match all open orders in the orderbook.
     */
    protected function matchAllOpenOrders(): void
    {
        // Get all open orders, oldest first (FIFO)
        $openOrders = Order::where('status', Order::STATUS_OPEN)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($openOrders as $order) {
            // Re-check status as it may have been filled by a previous iteration
            $order->refresh();
            if ($order->status === Order::STATUS_OPEN) {
                $this->matchOrder($order);
            }
        }
    }

    /**
     * Match a specific new order.
     */
    protected function matchOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Lock the order to ensure status is fresh
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($order->status !== Order::STATUS_OPEN) {
                return;
            }

            // Track the aggressor order for price determination
            $this->newOrder = $order;

            if ($order->side === Order::SIDE_BUY) {
                $this->matchBuyOrder($order);
            } else {
                $this->matchSellOrder($order);
            }
        });
    }

    /**
     * Match a buy order with available sell orders.
     */
    protected function matchBuyOrder(Order $buyOrder): void
    {
        // STRICT RULE: New BUY -> match with first SELL where sell.price <= buy.price
        // STRICT RULE: One match per order.
        // STRICT RULE: No Partial Fills.
        // Interpretation: We look for ONE sell order that can satisfy the buy order fully? 
        // Or one sell order that matches exactly? 
        // Given "One match per order", we can only execute one trade.
        // If Buy(1.0) and Sell(0.5) exist, we can't match because we'd need another sell order (2 matches).
        // So we need Sell.amount >= Buy.amount to fill Buy fully.
        // OR Buy.amount >= Sell.amount to fill Sell fully?
        // "Orders must be matched fully or not at all".
        // This implies if we match, at least one side closes. 
        // If we match Buy(1.0) with Sell(2.0), Buy is filled, Sell is partial. -> VIOLATION of "No partial fills".
        // Conclusion: Both sides must equal. EXACT AMOUNT MATCH.

        $sellOrder = Order::where('symbol', $buyOrder->symbol)
            ->where('side', Order::SIDE_SELL)
            ->where('status', Order::STATUS_OPEN)
            ->where('price', '<=', $buyOrder->price)
            ->where('amount', $buyOrder->amount) // STRICT: Exact match required
            ->where('user_id', '!=', $buyOrder->user_id)
            ->orderBy('price', 'asc') // Best price
            ->orderBy('created_at', 'asc') // FIFO
            ->lockForUpdate()
            ->first();

        if ($sellOrder) {
            $this->executeTrade($buyOrder, $sellOrder);
        }
    }

    /**
     * Match a sell order with available buy orders.
     */
    protected function matchSellOrder(Order $sellOrder): void
    {
        // STRICT RULE: New SELL -> match with first BUY where buy.price >= sell.price
        // STRICT RULE: Exact amount match.

        $buyOrder = Order::where('symbol', $sellOrder->symbol)
            ->where('side', Order::SIDE_BUY)
            ->where('status', Order::STATUS_OPEN)
            ->where('price', '>=', $sellOrder->price)
            ->where('amount', $sellOrder->amount) // STRICT: Exact match required
            ->where('user_id', '!=', $sellOrder->user_id)
            ->orderBy('price', 'desc') // Best price
            ->orderBy('created_at', 'asc') // FIFO
            ->lockForUpdate()
            ->first();

        if ($buyOrder) {
            $this->executeTrade($buyOrder, $sellOrder);
        }
    }

    /**
     * Execute a trade between two orders.
     */
    protected function executeTrade(Order $buyOrder, Order $sellOrder): void
    {
        // Use the maker's price (the one that was already in the book)
        // If incoming is Buy, Maker is Sell. Trade Price = Sell Price.
        // If incoming is Sell, Maker is Buy. Trade Price = Buy Price.
        
        // Wait, standard matching engine rule: Trade Price is the Maker's Price.
        // If Buy Order triggered this (Aggressor), Sell Order is Maker. Price = Sell Order Price.
        // If Sell Order triggered this (Aggressor), Buy Order is Maker. Price = Buy Order Price.
        
        // However, we are in a transaction initiated by handle().
        // We need to know who is the aggressor.
        // The aggressor is $this->newOrder.
        
        $price = null;
        if ($this->newOrder->id === $buyOrder->id) {
            // Buy is Aggressor, Sell is Maker
            $price = $sellOrder->price;
        } else {
            // Sell is Aggressor, Buy is Maker
            $price = $buyOrder->price;
        }

        $amount = $buyOrder->amount; // They are equal

        // Calculate Values with BCMath
        $totalValue = bcmul((string)$price, (string)$amount, 8);
        $commissionRate = '0.015';
        $commission = bcmul($totalValue, $commissionRate, 8);
        
        // Seller receives: TotalValue - Commission
        $sellerReceiveValue = bcsub($totalValue, $commission, 8);

        // CREATE TRADE RECORD (Atomic)
        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id' => $buyOrder->user_id,
            'seller_id' => $sellOrder->user_id,
            'symbol' => $buyOrder->symbol,
            'price' => $price,
            'amount' => $amount,
            'commission' => $commission,
        ]);

        $buyer = $buyOrder->user()->lockForUpdate()->first();
        $seller = $sellOrder->user()->lockForUpdate()->first();

        // BUYER LOGIC
        // 1. Receive Assets
        $buyerAsset = Asset::firstOrCreate(
            ['user_id' => $buyer->id, 'symbol' => $buyOrder->symbol],
            ['amount' => 0, 'locked_amount' => 0]
        );
        // Lock for update after creation/retrieval
        $buyerAsset = Asset::where('id', $buyerAsset->id)->lockForUpdate()->first();
        $buyerAsset->amount = bcadd((string)$buyerAsset->amount, (string)$amount, 8);
        $buyerAsset->save();

        // 2. Refresh/Refund USD
        // Buyer locked `buyOrder.price * amount`. Trade happened at `$price`.
        // Locked amount might be higher if `$price < buyOrder.price`.
        // Refund difference.
        $lockedUsd = bcmul((string)$buyOrder->price, (string)$amount, 8); // What was locked
        $actualCost = $totalValue; // what is paid ($price * amount)
        
        // Since price <= buyOrder.price (for Buy aggressor) OR price = buyOrder.price (for Sell aggressor),
        // lockedUsd >= actualCost.
        // We ALREADY deducted lockedUsd from balance.
        // So we refund ($lockedUsd - $actualCost).
        $refund = bcsub($lockedUsd, $actualCost, 8);
        if (bccomp($refund, '0', 8) === 1) {
            $buyer->balance = bcadd((string)$buyer->balance, $refund, 8);
        }
        $buyer->save();

        // SELLER LOGIC
        // 1. Give USD (Minus Commission)
        $seller->balance = bcadd((string)$seller->balance, $sellerReceiveValue, 8);
        $seller->save();

        // 2. Unlock Assets (Reduce locked_amount)
        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->first();
        
        // Deduct from locked_amount AND total amount because they are sold.
        // Note: When placing order, we moved amount to locked_amount.
        // So we strictly reduce locked_amount (and it implicitly reduces from total ownership visualization if we tracked total = available + locked, but here amount is available? No.)
        // Let's check Schema/Logic.
        // Asset Table: amount (Available), locked_amount (Reserved).
        // Wait. Usually `amount` is Total or Available?
        // Let's check earlier migration/logic.
        // Migration: "amount (available), locked_amount (reserved)".
        // Logic in OrderService: 
        //   Place Sell: "Check if (amount - locked) > req". NO. "Check if available > req".
        //   Then "locked += req".
        //   Wait. If 'amount' is 'available', then when we lock, we should REDUCE 'amount' and INCREASE 'locked'?
        //   OR 'amount' is TOTAL and 'locked' is a portion of it?
        //   Let's check OrderService logic I wrote:
        //   `$available = bcsub($asset->amount, $asset->locked_amount)`
        //   So `amount` is TOTAL Balance.
        //   When selling: `locked += amount`. Total `amount` stays same.
        //   When Filled: We must reduce `locked` AND `amount` (Total).
        
        $sellerAsset->locked_amount = bcsub((string)$sellerAsset->locked_amount, (string)$amount, 8);
        $sellerAsset->amount = bcsub((string)$sellerAsset->amount, (string)$amount, 8);
        
        // Safety clamps (though atomic logic should prevent this)
        if (bccomp((string)$sellerAsset->locked_amount, '0', 8) === -1) $sellerAsset->locked_amount = '0';
        if (bccomp((string)$sellerAsset->amount, '0', 8) === -1) $sellerAsset->amount = '0';
        
        $sellerAsset->save();

        // CLOSE ORDERS
        $buyOrder->status = Order::STATUS_FILLED;
        $buyOrder->amount = '0'; // Consumed
        $buyOrder->save();

        $sellOrder->status = Order::STATUS_FILLED;
        $sellOrder->amount = '0'; // Consumed
        $sellOrder->save();

        // BROADCAST (After Commit)
        // We are inside transaction, so we rely on Laravel's event queue + AfterCommit 
        // OR we manually dispatch after the transaction block in handle().
        // But broadcast() helper usually dispatches to queue. 
        // Better: Dispatch Broadcast Job or Event.
        event(new OrderMatched($trade));
    }
}
