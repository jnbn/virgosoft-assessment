<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Trade $trade
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-user.' . $this->trade->buyer_id),
            new PrivateChannel('private-user.' . $this->trade->seller_id),
            new Channel('orderbook.' . $this->trade->symbol),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $buyOrder = $this->trade->buyOrder;
        $sellOrder = $this->trade->sellOrder;
        $buyer = $this->trade->buyer;
        $seller = $this->trade->seller;
        
        // Get updated balances and assets
        $buyerAssets = $buyer->assets()->get()->map(function ($asset) {
            return [
                'symbol' => $asset->symbol,
                'amount' => (float) $asset->amount,
                'locked_amount' => (float) $asset->locked_amount,
                'available' => (float) ($asset->amount - $asset->locked_amount),
            ];
        })->values();
        
        $sellerAssets = $seller->assets()->get()->map(function ($asset) {
            return [
                'symbol' => $asset->symbol,
                'amount' => (float) $asset->amount,
                'locked_amount' => (float) $asset->locked_amount,
                'available' => (float) ($asset->amount - $asset->locked_amount),
            ];
        })->values();
        
        return [
            'trade' => [
                'id' => $this->trade->id,
                'symbol' => $this->trade->symbol,
                'price' => (float) $this->trade->price,
                'amount' => (float) $this->trade->amount,
                'buyer_id' => $this->trade->buyer_id,
                'seller_id' => $this->trade->seller_id,
                'created_at' => $this->trade->created_at->toIso8601String(),
            ],
            'buy_order' => [
                'id' => $buyOrder->id,
                'status' => $buyOrder->status,
                'amount' => (float) $buyOrder->amount,
            ],
            'sell_order' => [
                'id' => $sellOrder->id,
                'status' => $sellOrder->status,
                'amount' => (float) $sellOrder->amount,
            ],
            'buyer' => [
                'id' => $buyer->id,
                'balance' => (float) $buyer->balance,
                'assets' => $buyerAssets,
            ],
            'seller' => [
                'id' => $seller->id,
                'balance' => (float) $seller->balance,
                'assets' => $sellerAssets,
            ],
        ];
    }
}
