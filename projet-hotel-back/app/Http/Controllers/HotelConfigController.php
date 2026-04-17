<?php

namespace App\Http\Controllers;

use App\Models\HotelConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class HotelConfigController extends Controller
{
    public function __construct()
    {
        // show est public pour la landing page, update nécessite l'authentification
        $this->middleware('auth:api')->only(['update']);
    }

    /**
     * Récupère la configuration actuelle de l'hôtel. (PUBLIC)
     */
    public function show()
    {
        $config = HotelConfig::getConfig();
        return response()->json($config);
    }

    /**
     * Met à jour la configuration de l'hôtel.
     */
    public function update(Request $request)
    {
        // Vérifier que l'utilisateur est admin
        $user = auth()->user();
        if (!$user->hasRole('Super-Admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // Récupérer les données de la requête
        // Avec FormData, Laravel doit lire les données différemment
        // Essayer d'abord avec input() qui fonctionne avec FormData
        $requestData = $request->all();
        
        // Debug: Log détaillé des données reçues
        \Log::info('HotelConfig update request DEBUG', [
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'has_file' => $request->hasFile('paid_stamp'),
            'file_info' => $request->hasFile('paid_stamp') ? [
                'name' => $request->file('paid_stamp')->getClientOriginalName(),
                'size' => $request->file('paid_stamp')->getSize(),
            ] : null,
            'name_from_input' => $request->input('name'),
            'name_from_get' => $request->get('name'),
            'name_from_all' => $requestData['name'] ?? 'NOT_IN_ALL',
            'all_keys' => array_keys($requestData),
            'all_data_raw_count' => count($requestData),
        ]);
        
        // Récupérer les données - utiliser input() qui fonctionne avec FormData
        $data = [
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'logo' => $request->input('logo'),
            'description' => $request->input('description'),
        ];
        
        // S'assurer que le champ name existe et n'est pas vide
        if (empty($data['name']) || trim($data['name']) === '') {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => ['name' => ['Le nom de l\'hôtel est requis.']]
            ], 422);
        }
        
        // Normaliser le nom
        $data['name'] = trim($data['name']);
        
        // Convertir les chaînes vides en null pour les champs nullable
        foreach (['address', 'phone', 'email', 'logo', 'description'] as $field) {
            if (isset($data[$field]) && trim($data[$field]) === '') {
                $data[$field] = null;
            }
        }
        
        // Valider les champs texte
        // Note: Si un fichier est présent, la requête sera multipart/form-data
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|string|max:500',
            'paid_stamp' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
            'description' => 'nullable|string',
        ], [
            'name.required' => 'Le nom de l\'hôtel est requis.',
            'name.string' => 'Le nom de l\'hôtel doit être une chaîne de caractères.',
            'name.max' => 'Le nom de l\'hôtel ne doit pas dépasser 255 caractères.',
            'address.max' => 'L\'adresse ne doit pas dépasser 500 caractères.',
            'phone.max' => 'Le téléphone ne doit pas dépasser 50 caractères.',
            'email.email' => 'L\'email doit être une adresse email valide.',
            'email.max' => 'L\'email ne doit pas dépasser 255 caractères.',
            'logo.max' => 'Le logo ne doit pas dépasser 500 caractères.',
            'paid_stamp.image' => 'Le fichier doit être une image.',
            'paid_stamp.mimes' => 'Le fichier doit être de type: jpeg, jpg, png, gif ou webp.',
            'paid_stamp.max' => 'Le fichier ne doit pas dépasser 5MB.',
        ]);

        if ($validator->fails()) {
            \Log::error('HotelConfig validation failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->except(['paid_stamp']), // Exclure le fichier pour le log
                'has_file' => $request->hasFile('paid_stamp'),
            ]);
            
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $config = HotelConfig::getConfig();
        
        // Utiliser les données normalisées au lieu de $request->only()
        $updateData = [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'logo' => $data['logo'] ?? null,
            'description' => $data['description'] ?? null,
        ];
        
        // Gérer l'upload du tampon "PAYÉ" si fourni
        if ($request->hasFile('paid_stamp')) {
            // Supprimer l'ancien tampon s'il existe
            if ($config->paid_stamp && Storage::disk('public')->exists($config->paid_stamp)) {
                Storage::disk('public')->delete($config->paid_stamp);
            }
            
            // Enregistrer le nouveau tampon
            $path = $request->file('paid_stamp')->store('stamps', 'public');
            $updateData['paid_stamp'] = $path;
        }
        
        $config->update($updateData);

        return response()->json([
            'message' => 'Configuration mise à jour avec succès',
            'config' => $config->fresh()
        ]);
    }
}
