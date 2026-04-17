<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\MenuItem;
use App\Models\User;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Helpers\NotificationHelper;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except([
            'handleFedaPayCallback',
            'handleFedaPayRedirect',
        ]);
        
        FedaPay::setEnvironment(env('FEDAPAY_ENVIRONMENT', 'sandbox'));
        FedaPay::setApiKey(apiKey: env('FEDAPAY_SECRET_KEY'));
    }

    /**
     * Affiche la liste des commandes. (Personnel uniquement)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with(['orderItems.menuItem', 'user'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            // Recherche sur le nouveau champ 'location'
            $query->where('location', 'like', "%{$request->q}%");
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    /**
     * Liste les commandes de l'utilisateur connecté.
     */
    public function myOrders(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $perPage = max(1, intval($request->input('per_page', 10)));
        $query = Order::with('orderItems.menuItem')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Crée une nouvelle commande.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Order::class);

        $validated = $request->validate([
            'user_id'    => 'nullable|integer|exists:users,id',
            'order_type' => ['required', Rule::in([Order::TYPE_ROOM_SERVICE, Order::TYPE_RESTAURANT])],
            'location'   => 'required|string|max:255',
            'items'      => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|integer|exists:menu_items,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'note'       => 'nullable|string|max:1000',
        ]);

        $menuItems = MenuItem::find(collect($validated['items'])->pluck('menu_item_id'));
        $total = collect($validated['items'])->sum(function ($item) use ($menuItems) {
            $menuItem = $menuItems->find($item['menu_item_id']);
            return $menuItem->price * $item['quantity'];
        });

        // Utiliser le user_id fourni ou celui de l'utilisateur connecté
        $userId = $validated['user_id'] ?? $request->user()->id;
        $user = User::findOrFail($userId);

        // Créer la commande avec statut pending
        $order = DB::transaction(function () use ($validated, $userId, $total) {
            $newOrder = Order::create([
                'user_id'    => $userId,
                'order_type' => $validated['order_type'],
                'location'   => $validated['location'],
                'status'     => 'pending',
                'total'      => $total,
                'note'       => $validated['note'] ?? null,
            ]);

            $menuItems = MenuItem::find(collect($validated['items'])->pluck('menu_item_id'));
            foreach ($validated['items'] as $item) {
                $menuItem = $menuItems->find($item['menu_item_id']);
                $newOrder->orderItems()->create([
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $menuItem->price,
                ]);
            }
            return $newOrder;
        });

        // Préparer les données pour FedaPay
        $pendingKey = 'pending_order_' . (string) Str::uuid();
        $pendingData = [
            'order_id' => $order->id,
            'user_id' => $userId,
            'total' => $total,
            'created_at' => now()->toDateTimeString(),
        ];
        Cache::put($pendingKey, $pendingData, now()->addHour());

        try {
            $callbackUrl = route('api.fedapay.order.callback', ['pending_key' => $pendingKey]);
            $nameParts = explode(' ', $user->name);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            $phoneNumber = $request->input('phone_number', '00000000');

            $customerData = [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $user->email,
            ];

            if ($phoneNumber && $phoneNumber !== '00000000') {
                $customerData['phone_number'] = [
                    'number' => $phoneNumber,
                    'country' => 'TG'
                ];
            }

            $orderTypeLabel = $validated['order_type'] === Order::TYPE_ROOM_SERVICE ? 'Room Service' : 'Restaurant';
            $itemsDescription = collect($validated['items'])->map(function ($item) use ($menuItems) {
                $menuItem = $menuItems->find($item['menu_item_id']);
                return "{$item['quantity']}x {$menuItem->name}";
            })->join(', ');

            $transaction = Transaction::create([
                'description' => "Commande {$orderTypeLabel} - {$itemsDescription}",
                'amount' => intval($total),
                'currency' => ['iso' => 'XOF'],
                'callback_url' => $callbackUrl,
                'customer' => $customerData
            ]);

            $token = $transaction->generateToken();
        } catch (\Throwable $e) {
            Log::error('FedaPay order init failed', ['err' => $e->getMessage(), 'order_id' => $order->id]);
            Cache::forget($pendingKey);
            return response()->json(['error' => 'payment_init_failed', 'message' => 'Impossible d\'initialiser le paiement. ' . $e->getMessage()], 500);
        }

        if ($token) {
            $order->pending_key = $pendingKey;
            $order->transaction_id = $transaction->id ?? null;
            $order->save();

            Cache::put($pendingKey, array_merge($pendingData, [
                'transaction_id' => $transaction->id ?? null,
                'token_url' => $token->url
            ]), now()->addHour());

            return response()->json([
                'success' => true,
                'redirect_url' => $token->url,
                'pending_key' => $pendingKey,
                'message' => 'Redirection vers le paiement pour valider la commande',
                'data' => $order->load('orderItems.menuItem')
            ], 201);
        }

        Cache::forget($pendingKey);
        return response()->json(['error' => 'link_generation_failed'], 500);
    }

    /**
     * Callback FedaPay pour les commandes
     */
    public function handleFedaPayCallback(Request $request)
    {
        // Gérer les requêtes GET (redirections après annulation)
        if ($request->isMethod('GET')) {
            return $this->handleFedaPayRedirect($request);
        }

        // Gérer les requêtes POST (webhooks)
        try {
            $rawPayload = $request->getContent();
            $sigHeader = $request->header('X-FEDAPAY-SIGNATURE') ?? $request->header('FedaPay-Signature');
            $endpointSecret = env('FEDAPAY_WEBHOOK_SECRET');

            if ($endpointSecret && class_exists(\FedaPay\Webhook::class)) {
                $event = \FedaPay\Webhook::constructEvent($rawPayload, $sigHeader, $endpointSecret);
                $eventName = $event->name ?? null;
                $payloadData = $event->data ?? json_decode($rawPayload, true);
            } else {
                $payloadData = json_decode($rawPayload, true);
                $eventName = $payloadData['event'] ?? ($payloadData['type'] ?? null);
            }
        } catch (\Throwable $e) {
            Log::error('Webhook signature invalid', ['error' => $e->getMessage(), 'payload' => $request->all()]);
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $validEvents = ['transaction.approved', 'transaction.succeeded', 'transaction.updated'];
        if (! in_array($eventName, $validEvents)) {
            return response()->json(['ok' => true, 'message' => 'ignored_event'], 200);
        }

        $pendingKey = $request->query('pending_key') ?? ($payloadData['pending_key'] ?? null) ?? ($payloadData['data']['pending_key'] ?? null);
        if (! $pendingKey) {
            Log::warning('Webhook without pending_key', ['payload' => $payloadData]);
            return response()->json(['error' => 'missing_pending_key'], 400);
        }

        $order = Order::where('pending_key', $pendingKey)->first();
        if (! $order) {
            $cached = Cache::get($pendingKey);
            if ($cached && isset($cached['order_id'])) {
                $order = Order::find($cached['order_id']);
            }
        }
        
        if (! $order) {
            Log::warning('Pending order not found (db+cache)', ['pending_key' => $pendingKey]);
            return response()->json(['error' => 'order_not_found'], 404);
        }

        $transactionId = $order->transaction_id ?? ($payloadData['data']['id'] ?? $payloadData['id'] ?? null);
        if (! $transactionId) {
            Log::error('No transaction id', ['order_id' => $order->id, 'payload' => $payloadData]);
            return response()->json(['error' => 'transaction_id_missing'], 400);
        }

        try {
            $tx = \FedaPay\Transaction::retrieve($transactionId);
            $status = strtolower($tx->status ?? ($payloadData['data']['status'] ?? 'unknown'));
        } catch (\Throwable $e) {
            Log::error('FedaPay retrieve failed', ['err' => $e->getMessage(), 'txid' => $transactionId]);
            return response()->json(['error' => 'cannot_verify_payment'], 500);
        }

        if (in_array($status, ['approved', 'paid', 'completed', 'succeeded'])) {
            try {
                DB::transaction(function () use ($order) {
                    $ord = Order::lockForUpdate()->find($order->id);
                    // Marquer la commande comme prête automatiquement après paiement
                    $ord->status = 'ready';
                    $ord->save();
                });

                $order->refresh()->load('user');
                NotificationHelper::notifyAdminsOfNewOrder($order);

                Cache::forget($pendingKey);
                Log::info('Order confirmed after payment', ['order_id' => $order->id, 'tx' => $transactionId]);
                return response()->json(['ok' => true], 200);
            } catch (\Throwable $e) {
                Log::error('Failed to confirm order', ['err' => $e->getMessage()]);
                return response()->json(['error' => 'failed_to_confirm'], 500);
            }
        }

        if (in_array($status, ['declined', 'failed', 'canceled', 'rejected'])) {
            try {
                DB::transaction(function () use ($order) {
                    $ord = Order::lockForUpdate()->find($order->id);
                    $ord->status = 'cancelled';
                    $ord->save();
                });

                Cache::forget($pendingKey);
                Log::info('Order cancelled after failed payment', ['order_id' => $order->id, 'tx' => $transactionId]);
                return response()->json(['ok' => true], 200);
            } catch (\Throwable $e) {
                Log::error('Failed to cancel order', ['err' => $e->getMessage()]);
                return response()->json(['error' => 'failed_to_cancel'], 500);
            }
        }

        return response()->json(['ok' => true, 'message' => 'no_action_for_status', 'status' => $status], 200);
    }

    /**
     * Redirection après paiement FedaPay
     */
    public function handleFedaPayRedirect(Request $request)
    {
        $pendingKey = $request->query('pending_key');
        $status = $request->query('status');
        $transactionId = $request->query('id') ?? $request->query('transaction_id');

        Log::info('FedaPay redirect (order)', ['pendingKey' => $pendingKey, 'status' => $status, 'transactionId' => $transactionId]);

        if ($pendingKey) {
            $order = Order::where('pending_key', $pendingKey)->first();
            
            if ($order) {
                $paymentStatus = strtolower($status ?? 'unknown');
                
                if (in_array($paymentStatus, ['approved', 'paid', 'completed', 'succeeded'])) {
                    // Paiement réussi - marquer comme ready
                    try {
                        DB::transaction(function () use ($order) {
                            $ord = Order::lockForUpdate()->find($order->id);
                            if ($ord->status === 'pending') {
                                $ord->status = 'ready';
                                $ord->save();
                            }
                        });
                        Log::info('Order marked as ready after payment success', [
                            'order_id' => $order->id,
                            'pending_key' => $pendingKey
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to update order status after payment success', [
                            'err' => $e->getMessage(),
                            'order_id' => $order->id
                        ]);
                    }
                } elseif (in_array($paymentStatus, ['canceled', 'cancelled', 'declined', 'failed', 'rejected'])) {
                    // Paiement annulé/échoué
                    try {
                        DB::transaction(function () use ($order) {
                            $ord = Order::lockForUpdate()->find($order->id);
                            if (!in_array($ord->status, ['preparing', 'ready', 'delivered'])) {
                                $ord->status = 'cancelled';
                                $ord->save();
                            }
                        });
                        Log::info('Order cancelled after payment cancellation', [
                            'order_id' => $order->id,
                            'pending_key' => $pendingKey
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to cancel order after redirect', [
                            'err' => $e->getMessage(),
                            'order_id' => $order->id
                        ]);
                    }
                }
            }
        }

        $frontendBase = config('app.frontend_url');
        $redirectTo = $frontendBase . "/payment-result?pending_key=" . urlencode($pendingKey ?? '') . "&status=" . urlencode($status ?? '') . "&transaction_id=" . urlencode($transactionId ?? '');

        return redirect()->away($redirectTo);
    }

    /**
     * Affiche une commande spécifique.
     */
    public function show(Order $order)
    {
        $this->authorize('view', $order);
        return response()->json($order->load('orderItems.menuItem'));
    }

    /**
     * Met à jour le statut d'une commande.
     */
    public function update(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'preparing', 'ready', 'delivered', 'cancelled'])],
        ]);

        $order->update($validated);

        return response()->json(['message' => 'Statut mis à jour.', 'data' => $order->fresh()]);
    }

    /**
     * Annule/Supprime une commande.
     */
    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        $order->delete();

        return response()->json(['message' => 'Commande supprimée.']);
    }
}
