<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Récupère la liste de tous les rôles disponibles.
     */
    public function index()
    {
        // Seuls les admins peuvent voir les rôles
        $user = auth()->user();
        if (!$user->hasRole('Super-Admin') && !$user->hasPermissionTo('system-role-manage')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $roles = Role::where('guard_name', 'api')
            ->with('permissions')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                    'permissions_count' => $role->permissions->count(),
                ];
            });

        return response()->json($roles);
    }
}
