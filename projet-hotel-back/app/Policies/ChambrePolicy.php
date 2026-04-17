<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Chambre;

class ChambrePolicy
{
    public function before(User $user)
    {
        if ($user->hasRole('Super-Admin')) {
            return true;
        }
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('room-create');
    }

    public function update(User $user, Chambre $chambre): bool
    {
        return $user->hasPermissionTo('room-update');
    }

    public function delete(User $user, Chambre $chambre): bool
    {
        return $user->hasPermissionTo('room-delete');
    }
}
