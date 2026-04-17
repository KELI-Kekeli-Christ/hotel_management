<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('service-order-manage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        // Les utilisateurs peuvent voir leurs propres commandes, ou avoir la permission de gestion
        return $user->id === $order->user_id || $user->hasPermissionTo('service-order-manage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Les clients peuvent créer des commandes pour eux-mêmes, ou avoir la permission de gestion
        return true; // Tous les utilisateurs authentifiés peuvent créer des commandes
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->hasPermissionTo('service-order-manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->hasPermissionTo('service-order-manage');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        return $user->hasPermissionTo('service-order-manage');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->hasPermissionTo('service-order-manage');
    }
}
