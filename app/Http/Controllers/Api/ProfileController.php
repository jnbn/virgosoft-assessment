<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get user profile with balance and assets.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }
        
        $assets = $user->assets()->get()->map(function ($asset) {
            return [
                'symbol' => $asset->symbol,
                'amount' => (float) $asset->amount,
                'locked_amount' => (float) $asset->locked_amount,
                'available' => (float) ($asset->amount - $asset->locked_amount),
            ];
        })->values(); // Ensure it's a proper array, not a collection
        
        \Log::info('Profile API called', [
            'user_id' => $user->id,
            'assets_count' => $assets->count(),
            'balance' => $user->balance,
        ]);
        
        return response()->json([
            'balance' => (float) $user->balance,
            'assets' => $assets,
        ]);
    }
}

