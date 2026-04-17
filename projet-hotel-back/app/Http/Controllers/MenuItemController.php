<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MenuItemController extends Controller
{
    public function __construct()
    {
        // index est public, les autres méthodes nécessitent l'authentification
        $this->middleware('auth:api')->except(['index', 'show']);
    }

    /**
     * GET /api/menu-items (PUBLIC)
     * Pagination, recherche par nom/catégorie, filtre disponibilité.
     */
    public function index(Request $request)
    {
        // Permettre l'accès public pour la landing page
        $query = MenuItem::query()->where('available', true)->orderBy('name');

        if ($request->filled('q')) {
            $query->where('name', 'like', "%{$request->q}%");
        }
        
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $items = $query->paginate($perPage);
        
        // Ajouter l'URL complète de l'image
        $items->getCollection()->transform(function ($item) {
            if ($item->image) {
                $item->image_url = Storage::url($item->image);
            }
            return $item;
        });
        
        return response()->json($items);
    }

    /**
     * GET /api/menu-items/{id} (PUBLIC)
     */
    public function show(MenuItem $menuItem)
    {
        // Accès public si l'item est disponible
        if (!$menuItem->available) {
            return response()->json(['message' => 'Article non disponible'], 404);
        }
        
        // Ajouter l'URL complète de l'image
        if ($menuItem->image) {
            $menuItem->image_url = Storage::url($menuItem->image);
        }
        
        return response()->json($menuItem);
    }

    /**
     * POST /api/menu-items
     */
    public function store(Request $request)
    {
        $this->authorize('create', MenuItem::class);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'category'    => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'available'   => 'required|boolean',
            'image'       => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('menu-items', 'public');
        }

        $menuItem = MenuItem::create($validated);

        return response()->json(['message' => 'Article créé.', 'menuItem' => $menuItem], 201);
    }

    /**
     * PATCH /api/menu-items/{menuItem}
     */
    public function update(Request $request, MenuItem $menuItem)
    {
        $this->authorize('update', $menuItem);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'category'    => 'sometimes|required|string|max:255',
            'price'       => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'available'   => 'sometimes|required|boolean',
            'image'       => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ]);
        if ($request->hasFile('image')) {
            if ($menuItem->image) Storage::disk('public')->delete($menuItem->image);
            $validated['image'] = $request->file('image')->store('menu-items', 'public');
        }

        $menuItem->update($validated);

        return response()->json(['message' => 'Article mis à jour.', 'menuItem' => $menuItem->fresh()]);
    }

    /**
     * DELETE /api/menu-items/{menuItem}
     */
    public function destroy(MenuItem $menuItem)
    {
        $this->authorize('delete', $menuItem);

        if ($menuItem->image) {
            Storage::disk('public')->delete($menuItem->image);
        }
        $menuItem->delete();

        return response()->json(['message' => 'Article supprimé.']);
    }
}
