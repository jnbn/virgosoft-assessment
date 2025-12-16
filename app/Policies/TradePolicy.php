<?php

namespace App\Policies;

use App\Models\Trade;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TradePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Trade $trade): bool
    {
        // Users can view trades where they are either the buyer or seller
        return $user->id === $trade->buyer_id || $user->id === $trade->seller_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Trades are created automatically by the system when orders match
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Trade $trade): bool
    {
        // Trades are immutable - they represent executed matches
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Trade $trade): bool
    {
        // Trades should not be deleted as they represent historical transactions
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Trade $trade): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Trade $trade): bool
    {
        return false;
    }
}
