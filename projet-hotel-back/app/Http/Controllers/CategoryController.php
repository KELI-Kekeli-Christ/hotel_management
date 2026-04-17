<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
     /**
     * Protège toutes les méthodes de ce contrôleur.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Renvoie la liste de toutes les catégories.
     */
    public function index()
    {
        // Utiliser cache pour améliorer les performances, car les catégories changent rarement.
        $categories = cache()->remember('menu_categories', now()->addHour(), function () {
            return Category::all();
        });

        return response()->json($categories);
    }
}
