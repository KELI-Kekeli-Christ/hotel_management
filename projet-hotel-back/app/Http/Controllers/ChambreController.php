<?php

namespace App\Http\Controllers;

use App\Models\Chambre;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChambreRequest;
use App\Http\Requests\UpdateChambreRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ChambreController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index', 'show']);
    }

    /**
     * Affiche une liste paginée et filtrable des chambres. (Publique)
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $query = Chambre::query();

        // filter disponibilité: accepte '1','0','true','false'
        if ($request->filled('disponible')) {
            $val = $request->input('disponible');
            $bool = null;
            if (strtolower($val) === 'true' || $val === '1') {
                $bool = true;
            } elseif (strtolower($val) === 'false' || $val === '0') {
                $bool = false;
            }
            if ($bool !== null) {
                $query->where('disponibilite', $bool ? 1 : 0);
            }
        }

        // recherche libre
        if ($request->filled('q')) {
            $search = trim($request->input('q'));
            $query->where(function ($q) use ($search) {
                $q->where('numero_chambre', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Tri corrigé
        $sortBy = in_array($request->input('sort'), ['prix_nuite', 'numero_chambre', 'type', 'created_at'])
            ? $request->input('sort')
            : 'numero_chambre';
        $sortDir = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $chambres = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        // Transformation pour ajouter photo_url
        $chambres->getCollection()->transform(function ($chambre) {
            return $this->appendPhotoUrl($chambre);
        });

        return response()->json($chambres);
    }

    /**
     * GET /api/chambres/{id}
     */
    public function show(Chambre $chambre)
    {
        return response()->json($this->appendPhotoUrl($chambre));
    }

    /**
     * POST /api/chambres
     */
    public function store(StoreChambreRequest $request)
    {
        $this->authorize('create', Chambre::class);
        $validated = $request->validated();

        // Gestion de l'upload de photo
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('chambres', 'public');
        }

        $chambre = Chambre::create($validated);

        return response()->json([
            'message' => 'Chambre créée avec succès.',
            'chambre' => $this->appendPhotoUrl($chambre)
        ], 201);
    }

    /**
     * PUT/PATCH /api/chambres/{id}
     */
    public function update(UpdateChambreRequest $request, Chambre $chambre)
    {
        $this->authorize('update', $chambre);
        $validated = $request->validated();

        // Gestion de l'upload de photo
        if ($request->hasFile('photo')) {
            // Supprimer l'ancienne photo si elle existe
            if ($chambre->photo && Storage::disk('public')->exists($chambre->photo)) {
                Storage::disk('public')->delete($chambre->photo);
            }
            $validated['photo'] = $request->file('photo')->store('chambres', 'public');
        }

        $chambre->update($validated);

        return response()->json([
            'message' => 'Chambre mise à jour avec succès.',
            'chambre' => $this->appendPhotoUrl($chambre->fresh())
        ]);
    }

    /**
     * DELETE /api/chambres/{id}
     */
    public function destroy(Chambre $chambre)
    {
        $this->authorize('delete', $chambre);

        // Vérifier s'il y a des réservations actives ou futures
        if ($chambre->reservations()->where('date_depart', '>=', now()->toDateString())->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer une chambre avec des réservations actives ou futures.'
            ], 409);
        }

        DB::transaction(function () use ($chambre) {
            // Supprimer la photo si elle existe
            if ($chambre->photo && Storage::disk('public')->exists($chambre->photo)) {
                Storage::disk('public')->delete($chambre->photo);
            }
            $chambre->delete();
        });

        return response()->json(['message' => 'Chambre supprimée avec succès.']);
    }

    /**
     * Méthode privée pour ajouter l'URL complète de la photo.
     */
    private function appendPhotoUrl(Chambre $chambre): Chambre
    {
        if ($chambre->photo) {
            $chambre->photo_url = asset('storage/' . $chambre->photo);
        } else {
            $chambre->photo_url = null;
        }

        // Retirer le champ 'image' s'il existe (ligne corrigée)
        unset($chambre->image);

        return $chambre;
    }
}
