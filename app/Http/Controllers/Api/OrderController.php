<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Jobs\MatchOrders;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Services\OrderService;
use Exception;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Get all open orders for orderbook.
     * GET /api/orders?symbol=BTC
     */
    public function index(Request $request): JsonResponse
    {
        $symbol = $request->query('symbol');
        
        $query = Order::where('status', Order::STATUS_OPEN);
        
        if ($symbol) {
            $query->where('symbol', $symbol);
        }
        
        $orders = $query->orderBy('price', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'price' => (float) $order->price,
                    'amount' => (float) $order->amount,
                    'user_id' => $order->user_id,
                    'created_at' => $order->created_at->toIso8601String(),
                ];
            });
        
        return response()->json([
            'orders' => $orders,
        ]);
    }

    /**
     * Create a new limit order.
     * POST /api/orders
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->placeOrder($request->user(), $request->validated());

            return response()->json([
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order->id,
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'price' => (float) $order->price,
                    'amount' => (float) $order->amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel an open order and release locked funds.
     * POST /api/orders/{id}/cancel
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        try {
            $cancelledOrder = $this->orderService->cancelOrder($request->user(), $order);

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => [
                    'id' => $cancelledOrder->id,
                    'status' => $cancelledOrder->status,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

