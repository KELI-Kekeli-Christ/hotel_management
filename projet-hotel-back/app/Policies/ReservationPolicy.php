<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReservationPolicy
{
    /**
     * Règle pour voir la liste de TOUTES les réservations.
     * Seuls les utilisateurs avec la permission peuvent.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('reservation-list-all');
    }

    /**
     * Règle pour voir UNE réservation spécifique.
     * L'utilisateur doit être le propriétaire OU avoir la permission de tout voir.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        return $user->id === $reservation->user_id || $user->hasPermissionTo('reservation-view-any');
    }

   /**
     * Règle pour créer une réservation.
     * L'utilisateur doit avoir la permission de créer pour lui-même OU pour les autres.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('reservation-create-own') || $user->hasPermissionTo('reservation-create-for-others');
    }


    /**
     * Règle pour mettre à jour une réservation.
     * L'utilisateur doit être le propriétaire OU avoir la permission d'annuler/modifier.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        // Le propriétaire peut modifier sa propre réservation OU avoir la permission admin
        return $user->id === $reservation->user_id || $user->hasPermissionTo('reservation-update-any');
    }

     /**
     * Règle pour supprimer/annuler une réservation.
     * Même logique que la mise à jour.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->hasPermissionTo('reservation-delete-any');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Reservation $reservation): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Reservation $reservation): bool
    {
        //
    }
}
