<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display the trading dashboard.
     */
    public function index(Request $request): Response
    {
        $orders = $request->user()
            ->orders()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return Inertia::render('orders/Index', [
            'orders' => $orders,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('orders/Create');
    }

    /**
     * Store a newly created resource in storage.
     * This now redirects to API endpoint for actual creation.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
    {
        // Redirect to API endpoint - frontend will handle the API call
        return redirect()->route('orders.index')->with('success', 'Order placed successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }

}
