<?php

namespace App\Policies;

use App\Models\User;

class MenuItemPolicy
{
    // Tous les utilisateurs authentifiés peuvent voir le menu, mais seuls ceux avec la permission peuvent le gérer.
    public function viewAny(User $user): bool
    {
        // Tous les utilisateurs authentifiés peuvent voir le menu
        return true;
    }
    public function view(User $user): bool
    {
        // Tous les utilisateurs authentifiés peuvent voir un article du menu
        return true;
    }
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('service-menu-manage');
    }
    public function update(User $user): bool
    {
        return $user->hasPermissionTo('service-menu-manage');
    }
    public function delete(User $user): bool
    {
        return $user->hasPermissionTo('service-menu-manage');
    }
}
