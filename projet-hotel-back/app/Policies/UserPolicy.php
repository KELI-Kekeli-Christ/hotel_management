<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Vérifie si un Super-Admin peut effectuer n'importe quelle action.
     * C'est un "catch-all" qui simplifie les autres méthodes.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super-Admin')) {
            return true;
        }
        return null;
    }

    /**
     * L'utilisateur peut-il voir la liste des utilisateurs ?
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('user-list');
    }

    /**
     * L'utilisateur peut-il voir un profil spécifique ?
     * (Soit le sien, soit s'il a la permission de voir n'importe lequel)
     */
    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return $user->hasPermissionTo('user-view-self');
        }
        return $user->hasPermissionTo('user-view');
    }

    /**
     * L'utilisateur peut-il créer un nouvel utilisateur ?
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('user-create');
    }

    /**
     * L'utilisateur peut-il mettre à jour un profil ?
     * (Soit le sien, soit s'il a la permission de mettre à jour n'importe lequel)
     */
    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return $user->hasPermissionTo('user-update-self');
        }
        return $user->hasPermissionTo('user-update');
    }

    /**
     * L'utilisateur peut-il supprimer un utilisateur ?
     */
    public function delete(User $user, User $model): bool
    {
        // On empêche un utilisateur de se supprimer lui-même via cette méthode.
        if ($user->id === $model->id) {
            return false;
        }
        return $user->hasPermissionTo('user-delete');
    }
}
