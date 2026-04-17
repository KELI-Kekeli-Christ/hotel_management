<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Reservation;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardStatsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Retourne les statistiques générales du dashboard
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Période de comparaison (par défaut: dernière période équivalente)
        $period = $request->input('period', 'month'); // day, week, month
        
        // Parser et normaliser les dates
        if ($request->input('start_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
        } else {
            $startDate = Carbon::now()->startOfMonth()->startOfDay();
        }
        
        if ($request->input('end_date')) {
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
        } else {
            $endDate = Carbon::now()->endOfMonth()->endOfDay();
        }
        
        // Log pour debug
        \Log::info('Dashboard stats request', [
            'start_date_input' => $request->input('start_date'),
            'end_date_input' => $request->input('end_date'),
            'period' => $period,
            'parsed_start' => $startDate->toDateTimeString(),
            'parsed_end' => $endDate->toDateTimeString(),
        ]);

        // Calculer la période précédente pour la comparaison
        $daysDiff = $startDate->diffInDays($endDate) + 1; // +1 pour inclure les deux dates
        $previousStart = $startDate->copy()->subDays($daysDiff)->startOfDay();
        $previousEnd = $startDate->copy()->subDay()->endOfDay();

        // 1. Nombre total de clients (utilisateurs avec le rôle Client) dans la période
        $totalCustomers = User::role('Client')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $previousCustomers = User::role('Client')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $customersVariation = $previousCustomers > 0 
            ? round((($totalCustomers - $previousCustomers) / $previousCustomers) * 100, 1)
            : 0;

        // 2. Nombre de réservations (conversions)
        $totalReservations = Reservation::whereBetween('created_at', [$startDate, $endDate])->count();
        $previousReservations = Reservation::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $reservationsVariation = $previousReservations > 0
            ? round((($totalReservations - $previousReservations) / $previousReservations) * 100, 1)
            : ($totalReservations > 0 ? 100 : 0);

        // 3. Revenus totaux (factures payées + réservations confirmées)
        // Utiliser issued_date pour les factures si disponible, sinon created_at
        $revenue = Invoice::where('status', Invoice::STATUS_PAID)
            ->where(function($query) use ($startDate, $endDate) {
                // Factures avec issued_date dans la période OU sans issued_date mais avec created_at dans la période
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->whereNotNull('issued_date')
                      ->whereBetween('issued_date', [$startDate, $endDate]);
                })
                ->orWhere(function($q) use ($startDate, $endDate) {
                    $q->whereNull('issued_date')
                      ->whereBetween('created_at', [$startDate, $endDate]);
                });
            })
            ->sum('total_amount');
        
        // Ajouter les revenus des réservations confirmées (sans facture générée)
        $reservationsRevenue = Reservation::where('status', 'confirmed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereDoesntHave('invoice')
            ->with('chambre')
            ->get()
            ->sum(function ($reservation) {
                return $reservation->chambre ? ($reservation->chambre->prix_nuite * $reservation->dure_reservation) : 0;
            });
        
        $totalRevenue = $revenue + $reservationsRevenue;

        $previousRevenue = Invoice::where('status', Invoice::STATUS_PAID)
            ->where(function($query) use ($previousStart, $previousEnd) {
                $query->where(function($q) use ($previousStart, $previousEnd) {
                    $q->whereNotNull('issued_date')
                      ->whereBetween('issued_date', [$previousStart, $previousEnd]);
                })
                ->orWhere(function($q) use ($previousStart, $previousEnd) {
                    $q->whereNull('issued_date')
                      ->whereBetween('created_at', [$previousStart, $previousEnd]);
                });
            })
            ->sum('total_amount');
        
        $previousReservationsRevenue = Reservation::where('status', 'confirmed')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->whereDoesntHave('invoice')
            ->with('chambre')
            ->get()
            ->sum(function ($reservation) {
                return $reservation->chambre ? ($reservation->chambre->prix_nuite * $reservation->dure_reservation) : 0;
            });
        
        $previousTotalRevenue = $previousRevenue + $previousReservationsRevenue;
        
        $revenueVariation = $previousTotalRevenue > 0
            ? round((($totalRevenue - $previousTotalRevenue) / $previousTotalRevenue) * 100, 1)
            : ($totalRevenue > 0 ? 100 : 0);

        // 4. Nombre de commandes (orders) - toutes les commandes sauf annulées
        $totalOrders = Order::where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $previousOrders = Order::where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $ordersVariation = $previousOrders > 0
            ? round((($totalOrders - $previousOrders) / $previousOrders) * 100, 1)
            : ($totalOrders > 0 ? 100 : 0);

        // 5. Revenus par période pour le graphique
        $revenueChart = $this->getRevenueChart($startDate, $endDate, $period);

        // 6. Dernières transactions (factures payées + commandes récentes)
        $recentTransactions = $this->getRecentTransactions(5);

        // Debug: Log les résultats
        \Log::info('Dashboard stats calculated', [
            'customers' => $totalCustomers,
            'reservations' => $totalReservations,
            'revenue' => $totalRevenue,
            'orders' => $totalOrders,
            'revenue_breakdown' => [
                'invoices' => $revenue,
                'reservations' => $reservationsRevenue,
            ],
        ]);

        return response()->json([
            'stats' => [
                'customers' => [
                    'value' => $totalCustomers,
                    'variation' => $customersVariation,
                ],
                'reservations' => [
                    'value' => $totalReservations,
                    'variation' => $reservationsVariation,
                ],
                'revenue' => [
                    'value' => $totalRevenue,
                    'variation' => $revenueVariation,
                ],
                'orders' => [
                    'value' => $totalOrders,
                    'variation' => $ordersVariation,
                ],
            ],
            'chart' => $revenueChart,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    /**
     * Génère les données pour le graphique de revenus
     */
    private function getRevenueChart($startDate, $endDate, $period)
    {
        $data = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $periodEnd = match($period) {
                'day' => $current->copy()->endOfDay(),
                'week' => $current->copy()->endOfWeek(),
                'month' => $current->copy()->endOfMonth(),
                default => $current->copy()->endOfDay(),
            };

            if ($periodEnd > $endDate) {
                $periodEnd = $endDate;
            }

            $revenue = Invoice::where('status', Invoice::STATUS_PAID)
                ->where(function($query) use ($current, $periodEnd) {
                    $query->where(function($q) use ($current, $periodEnd) {
                        $q->whereNotNull('issued_date')
                          ->whereBetween('issued_date', [$current, $periodEnd]);
                    })
                    ->orWhere(function($q) use ($current, $periodEnd) {
                        $q->whereNull('issued_date')
                          ->whereBetween('created_at', [$current, $periodEnd]);
                    });
                })
                ->sum('total_amount');

            $reservationsRevenue = Reservation::where('status', 'confirmed')
                ->whereBetween('created_at', [$current, $periodEnd])
                ->whereDoesntHave('invoice')
                ->with('chambre')
                ->get()
                ->sum(function ($reservation) {
                    return $reservation->chambre ? ($reservation->chambre->prix_nuite * $reservation->dure_reservation) : 0;
                });

            $data[] = [
                'date' => $current->toDateString(),
                'amount' => $revenue + $reservationsRevenue,
            ];

            $current = match($period) {
                'day' => $current->copy()->addDay(),
                'week' => $current->copy()->addWeek(),
                'month' => $current->copy()->addMonth(),
                default => $current->copy()->addDay(),
            };
        }

        return $data;
    }

    /**
     * Récupère les dernières transactions (factures et commandes)
     */
    private function getRecentTransactions($limit = 5)
    {
        $invoices = Invoice::with('reservation.user')
            ->where('status', Invoice::STATUS_PAID)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'type' => 'invoice',
                    'date' => $invoice->created_at->toISOString(),
                    'status' => 'paid',
                    'email' => $invoice->reservation->user->email ?? 'N/A',
                    'amount' => $invoice->total_amount,
                ];
            });

        $orders = Order::with('user')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'type' => 'order',
                    'date' => $order->created_at->toISOString(),
                    'status' => 'paid',
                    'email' => $order->user->email ?? 'N/A',
                    'amount' => $order->total,
                ];
            });

        return $invoices->merge($orders)
            ->sortByDesc('date')
            ->take($limit)
            ->values()
            ->all();
    }
}

