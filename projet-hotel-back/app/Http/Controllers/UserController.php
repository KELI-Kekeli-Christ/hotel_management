<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    public function __construct()
    {
        // Guard API (JWT) — change 'api' si ton guard a un autre nom
        $this->middleware('auth:api');
    }

    /**
     * Affiche une liste paginée des utilisateurs.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn($qb) =>
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%")
            );
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage > 0 ? $perPage : 15;

        return response()->json($query->latest()->paginate($perPage));
    }

    /**
     * Crée un nouvel utilisateur (action administrative).
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        if (\method_exists($user, 'assignRole') && isset($validated['role'])) {
            $user->assignRole($validated['role']);
        }

        return response()->json(['message' => 'Utilisateur créé avec succès.', 'user' => $user->load('roles')], 201);
    }

    /**
     * Affiche un utilisateur spécifique.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);
        return response()->json($user->load('roles'));
    }

    /**
     * Met à jour un utilisateur spécifique.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role'     => ['sometimes', 'string', Rule::exists('roles', 'name')],
        ]);

        if (isset($validated['role'])) {
            $current = auth()->user();
            if (! $current || ! $current->hasPermissionTo('user-update-role')) {
                unset($validated['role']);
            }
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json(['message' => 'Utilisateur mis à jour.', 'user' => $user->fresh()->load('roles')]);
    }

    /**
     * Supprime un utilisateur.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        if ($user->hasRole('Super-Admin') && User::role('Super-Admin')->count() === 1) {
            return response()->json(['message' => 'Impossible de supprimer le dernier Super-Admin.'], 403);
        }

        if ($user->reservations()->where('date_depart', '>=', now()->toDateString())->exists()) {
            return response()->json(['message' => 'Cet utilisateur a des réservations actives ou futures.'], 409);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    /**
     * Récupère l'utilisateur actuellement authentifié.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $user->load('roles', 'permissions');

        return response()->json($user);
    }
}
