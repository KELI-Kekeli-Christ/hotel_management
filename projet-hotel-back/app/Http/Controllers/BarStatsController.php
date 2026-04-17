<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Http\Controllers\Controller;

class BarStatsController extends Controller
{
    public function __construct()
    {
        // 1. S'assure que l'utilisateur est authentifié.
        $this->middleware('auth:api');
        // 2. S'assure que l'utilisateur a la permission spécifique de voir les stats.
        $this->middleware('can:service-stats-view');
    }

    public function index()
    {
        // Chiffre d'affaires aujourd'hui
        $todayRevenue = Order::whereDate('created_at', Carbon::today())
            ->sum('total');

        // Nombre de commandes ce mois
        $monthlyOrders = Order::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        // Article le plus populaire
        $topItem = OrderItem::selectRaw('menu_items.name, SUM(order_items.quantity) as total_quantity')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->groupBy('menu_items.name')
            ->orderByDesc('total_quantity')
            ->first();

        // Top 5 articles les plus vendus
        $topItems = OrderItem::selectRaw('menu_items.name, SUM(order_items.quantity) as total_quantity')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->groupBy('menu_items.name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'total_quantity' => (int) $item->total_quantity
                ];
            });

        // 5 dernières commandes
        $recentOrders = Order::select(['id', 'total', 'created_at as date', 'location', 'order_type'])
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'todayRevenue'  => $todayRevenue,
            'monthlyOrders' => $monthlyOrders,
            'topItem'       => $topItem->name ?? 'N/A',
            'topItems'      => $topItems,
            'recentOrders'  => $recentOrders
        ]);
    }
}
