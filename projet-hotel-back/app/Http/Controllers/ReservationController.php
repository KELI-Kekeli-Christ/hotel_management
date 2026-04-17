<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Chambre;
use App\Models\Reservation;
use FedaPay\FedaPay;
use FedaPay\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Helpers\NotificationHelper;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Jobs\ReleaseRoomAfterCheckout;

class ReservationController extends Controller
{
    public function __construct()
    {
        // Middleware JWT guard (auth:api)
        $this->middleware('auth:api')->except([
            'handleFedaPayCallback',
            'handleFedaPayRedirect',
            'pendingStatus'
        ]);

        FedaPay::setEnvironment(env('FEDAPAY_ENVIRONMENT', 'sandbox'));
        FedaPay::setApiKey(apiKey: env('FEDAPAY_SECRET_KEY'));
    }

    /**
     * Liste paginée des réservations (admin/réceptionniste).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Reservation::class);

        $perPage = max(1, intval($request->input('per_page', 15)));

        $query = Reservation::with(['user', 'chambre'])->orderBy('date_arrive', 'desc');

        $query->when($request->filled('status'), fn($q) => $q->where('status', $request->status));
        $query->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id));
        $query->when($request->filled('chambre_id'), fn($q) => $q->where('chambre_id', $request->chambre_id));

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();
            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('date_arrive', [$start, $end])
                  ->orWhereBetween('date_depart', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('date_arrive', '<=', $start)
                         ->where('date_depart', '>=', $end);
                  });
            });
        }

        $reservations = $query->paginate($perPage);
        return response()->json($reservations);
    }

    /**
     * Création d'une réservation.
     */
    public function store(StoreReservationRequest $request)
    {
        $this->authorize('create', Reservation::class);

        $validated = $request->validated();
        $authUser = auth()->user();

        try {
            // Vérifier qu'on a bien un user authentifié (défensive)
            if (! $authUser) {
                return response()->json(['message' => 'Non authentifié.'], 401);
            }

            // Déterminer le user_id final
            $targetUserId = $validated['user_id'] ?? $authUser->id;
            
            // Autorisation: réserver pour un autre ?
            if ($targetUserId != $authUser->id) {
                // L'utilisateur essaie de réserver pour quelqu'un d'autre
                if (! $authUser->can('reservation-create-for-others')) {
                    return response()->json(['message' => 'Vous n\'avez pas la permission de réserver pour un autre utilisateur.'], 403);
                }
            } else {
                // L'utilisateur réserve pour lui-même
                if (! $authUser->can('reservation-create-own') && ! $authUser->can('reservation-create-for-others')) {
                    return response()->json(['message' => 'Vous n\'avez pas la permission de créer une réservation.'], 403);
                }
            }
            
            // Mettre à jour validated avec le user_id correct
            $validated['user_id'] = $targetUserId;

            $dateArrive = Carbon::parse($validated['date_arrive']);
            $dateDepart = Carbon::parse($validated['date_depart']);
            $dureeReservation = $dateArrive->diffInDays($dateDepart);
            if ($dureeReservation <= 0) {
                return response()->json(['message' => 'Dates invalides'], 400);
            }

            $chambre = Chambre::findOrFail($validated['chambre_id']);
            
            // Vérifier le flag de disponibilité général
            if (! $chambre->disponibilite) {
                return response()->json(['message' => "Cette chambre n'est actuellement pas disponible"], 400);
            }

            // Vérif chevauchement (seulement pour les réservations actives)
            $overlap = Reservation::where('chambre_id', $validated['chambre_id'])
                ->where('date_arrive', '<', $dateDepart)
                ->where('date_depart', '>', $dateArrive)
                ->whereNotIn('status', [
                    Reservation::STATUS_CANCELLED,
                    Reservation::STATUS_FAILED,
                    Reservation::STATUS_EXPIRED,
                    'cancelled',
                    'failed',
                    'expired'
                ])
                ->exists();
            if ($overlap) {
                return response()->json(['message' => 'La chambre est déjà réservée pour cette période'], 400);
            }

            // Flux unifié : tous les utilisateurs (admin et client) passent par FedaPay
            $client = User::findOrFail($validated['user_id']);
            $montantTotal = intval($chambre->prix_nuite * $dureeReservation);

            $reservation = DB::transaction(function () use ($validated, $dureeReservation) {
                $res = Reservation::create([
                    'user_id' => $validated['user_id'],
                    'chambre_id' => $validated['chambre_id'],
                    'numero_reservation' => 'TEMP-' . strtoupper(uniqid()),
                    'date_arrive' => $validated['date_arrive'],
                    'date_depart' => $validated['date_depart'],
                    'dure_reservation' => $dureeReservation,
                    'status' => Reservation::STATUS_PENDING ?? 'pending',
                ]);
                $ch = Chambre::findOrFail($validated['chambre_id']);
                $ch->disponibilite = 0;
                $ch->save();
                return $res;
            });

            $pendingKey = 'pending_res_' . (string) Str::uuid();
            $pendingData = [
                'reservation_id' => $reservation->id,
                'user_id' => $validated['user_id'],
                'chambre_id' => $validated['chambre_id'],
                'date_arrive' => $validated['date_arrive'],
                'date_depart' => $validated['date_depart'],
                'dure_reservation' => $dureeReservation,
                'montant_total' => $montantTotal,
                'created_at' => now()->toDateTimeString(),
            ];
            Cache::put($pendingKey, $pendingData, now()->addHour());

            try {
                $callbackUrl = route('api.fedapay.callback', ['pending_key' => $pendingKey]);
                $nameParts = explode(' ', $client->name);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
                $phoneNumber = $request->input('phone_number', '00000000'); // Valeur par défaut si non fournie

                $customerData = [
                    'firstname' => $firstName,
                    'lastname' => $lastName,
                    'email' => $client->email,
                ];

                // Ajouter phone_number seulement si fourni
                if ($phoneNumber && $phoneNumber !== '00000000') {
                    $customerData['phone_number'] = [
                        'number' => $phoneNumber,
                        'country' => 'TG'
                    ];
                }

                $jourLabel = $dureeReservation > 1 ? 'jours' : 'jour';
                $dateArriveFormatted = Carbon::parse($validated['date_arrive'])->format('d/m/Y');
                $dateDepartFormatted = Carbon::parse($validated['date_depart'])->format('d/m/Y');
                $transaction = Transaction::create([
                    'description' => "Paiement réservation chambre {$chambre->type} (n°{$chambre->numero_chambre}) - {$dureeReservation} {$jourLabel} - Du {$dateArriveFormatted} au {$dateDepartFormatted}",
                    'amount' => $montantTotal,
                    'currency' => ['iso' => 'XOF'],
                    'callback_url' => $callbackUrl,
                    'customer' => $customerData
                ]);

                $token = $transaction->generateToken();
            } catch (\Throwable $e) {
                Log::error('FedaPay init failed', ['err' => $e->getMessage(), 'reservation_id' => $reservation->id]);
                DB::transaction(function () use ($reservation) {
                    $reservation->status = Reservation::STATUS_FAILED ?? 'failed';
                    $reservation->save();
                    $ch = Chambre::findOrFail($reservation->chambre_id);
                    $ch->disponibilite = 1;
                    $ch->save();
                });
                
                // Message d'erreur plus clair pour l'utilisateur
                $errorMessage = 'Impossible d\'initialiser le paiement. ';
                if (str_contains(strtolower($e->getMessage()), 'authentification') || str_contains(strtolower($e->getMessage()), 'authentication')) {
                    $errorMessage .= 'Veuillez contacter l\'administrateur. (Configuration de paiement manquante)';
                } else {
                    $errorMessage .= $e->getMessage();
                }
                
                return response()->json(['error' => 'payment_init_failed', 'message' => $errorMessage], 500);
            }

            if ($token) {
                $reservation->pending_key = $pendingKey;
                $reservation->transaction_id = $transaction->id ?? null;
                $reservation->save();

                Cache::put($pendingKey, array_merge($pendingData, [
                    'transaction_id' => $transaction->id ?? null,
                    'token_url' => $token->url
                ]), now()->addHour());

                return response()->json([
                    'success' => true,
                    'redirect_url' => $token->url,
                    'pending_key' => $pendingKey,
                    'reservation' => $reservation
                ], 200);
            }

            DB::transaction(function () use ($reservation) {
                $reservation->status = Reservation::STATUS_FAILED ?? 'failed';
                $reservation->save();
                $ch = Chambre::findOrFail($reservation->chambre_id);
                $ch->disponibilite = 1;
                $ch->save();
            });

            return response()->json(['error' => 'link_generation_failed'], 500);
        } catch (\Throwable $e) {
            Log::error('Reservation::store exception', ['err' => $e->getMessage()]);
            return response()->json(['error' => 'server_error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Affiche une réservation spécifique.
     */
    public function show(Reservation $reservation)
    {
        $this->authorize('view', $reservation);
        return response()->json($reservation->load(['user', 'chambre']));
    }

    /**
     * Met à jour une réservation.
     */
    public function update(UpdateReservationRequest $request, Reservation $reservation)
    {
        $this->authorize('update', $reservation);

        $validated = $request->validated();
        
        // Charger les relations nécessaires
        $reservation->load(['user', 'chambre']);
        $chambre = $reservation->chambre;
        
        // Calculer les nouvelles dates et durée
        $dateArrive = isset($validated['date_arrive']) ? Carbon::parse($validated['date_arrive']) : Carbon::parse($reservation->date_arrive);
        $dateDepart = isset($validated['date_depart']) ? Carbon::parse($validated['date_depart']) : Carbon::parse($reservation->date_depart);
        $dureeReservation = $dateArrive->diffInDays($dateDepart);
        
        if ($dureeReservation <= 0) {
            return response()->json(['message' => 'Dates invalides'], 400);
        }
        
        // Calculer l'ancien montant total (basé sur les dates actuelles de la réservation)
        $ancienneDuree = Carbon::parse($reservation->date_arrive)->diffInDays(Carbon::parse($reservation->date_depart));
        $ancienMontantTotal = intval($chambre->prix_nuite * $ancienneDuree);
        
        // Calculer le nouveau montant total
        $nouveauMontantTotal = intval($chambre->prix_nuite * $dureeReservation);
        
        // Récupérer le pourcentage de paiement pour modification
        $paymentPercentage = config('app.reservation_modification_payment_percentage', 20);
        if ($paymentPercentage <= 0 || $paymentPercentage > 100) {
            return response()->json(['message' => 'Pourcentage de paiement invalide'], 500);
        }
        
        // Calculer le montant à payer selon la logique :
        // - Si durée inchangée ou réduite : payer seulement le pourcentage du nouveau montant
        // - Si durée augmentée : payer le pourcentage du nouveau montant + le surplus
        if ($dureeReservation <= $ancienneDuree) {
            // Durée inchangée ou réduite : payer seulement le pourcentage du nouveau montant
            $montantAPayer = intval($nouveauMontantTotal * ($paymentPercentage / 100));
        } else {
            // Durée augmentée : payer le pourcentage du nouveau montant + le surplus
            $surplus = $nouveauMontantTotal - $ancienMontantTotal;
            $acompte = intval($nouveauMontantTotal * ($paymentPercentage / 100));
            $montantAPayer = $acompte + $surplus;
        }
        
        if ($montantAPayer <= 0) {
            return response()->json(['message' => 'Le montant à payer doit être supérieur à zéro'], 400);
        }
        
        // Créer une clé unique pour cette modification
        $pendingKey = 'pending_update_' . (string) Str::uuid();
        $pendingData = [
            'reservation_id' => $reservation->id,
            'user_id' => $reservation->user_id,
            'chambre_id' => $reservation->chambre_id,
            'date_arrive' => $dateArrive->format('Y-m-d'),
            'date_depart' => $dateDepart->format('Y-m-d'),
            'dure_reservation' => $dureeReservation,
            'montant_total' => $nouveauMontantTotal,
            'montant_paye' => $montantAPayer,
            'ancien_montant_total' => $ancienMontantTotal,
            'is_update' => true,
            'created_at' => now()->toDateTimeString(),
        ];
        Cache::put($pendingKey, $pendingData, now()->addHour());
        
        try {
            $callbackUrl = route('api.fedapay.callback', ['pending_key' => $pendingKey]);
            $nameParts = explode(' ', $reservation->user->name);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            $phoneNumber = $request->input('phone_number', '00000000');
            
            $customerData = [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $reservation->user->email,
            ];
            
            if ($phoneNumber && $phoneNumber !== '00000000') {
                $customerData['phone_number'] = [
                    'number' => $phoneNumber,
                    'country' => 'TG'
                ];
            }
            
            $jourLabel = $dureeReservation > 1 ? 'jours' : 'jour';
            $dateArriveFormatted = $dateArrive->format('d/m/Y');
            $dateDepartFormatted = $dateDepart->format('d/m/Y');
            
            // Construire la description selon le cas
            if ($dureeReservation <= $ancienneDuree) {
                $description = "Modification réservation chambre {$chambre->type} (n°{$chambre->numero_chambre}) - {$dureeReservation} {$jourLabel} - Du {$dateArriveFormatted} au {$dateDepartFormatted} - Acompte ({$paymentPercentage}%)";
            } else {
                $surplus = $nouveauMontantTotal - $ancienMontantTotal;
                $description = "Modification réservation chambre {$chambre->type} (n°{$chambre->numero_chambre}) - {$dureeReservation} {$jourLabel} - Du {$dateArriveFormatted} au {$dateDepartFormatted} - Acompte ({$paymentPercentage}%) + Surplus";
            }
            
            $transaction = Transaction::create([
                'description' => $description,
                'amount' => $montantAPayer,
                'currency' => ['iso' => 'XOF'],
                'callback_url' => $callbackUrl,
                'customer' => $customerData
            ]);
            
            $token = $transaction->generateToken();
        } catch (\Throwable $e) {
            Log::error('FedaPay update init failed', ['err' => $e->getMessage(), 'reservation_id' => $reservation->id]);
            Cache::forget($pendingKey);
            return response()->json(['error' => 'payment_init_failed', 'message' => 'Impossible d\'initialiser le paiement pour la modification. ' . $e->getMessage()], 500);
        }
        
        if ($token) {
            // Sauvegarder le pending_key et transaction_id dans la réservation (temporairement)
            $reservation->pending_key = $pendingKey;
            $reservation->transaction_id = $transaction->id ?? null;
            $reservation->save();
            
            Cache::put($pendingKey, array_merge($pendingData, [
                'transaction_id' => $transaction->id ?? null,
                'token_url' => $token->url
            ]), now()->addHour());
            
            return response()->json([
                'success' => true,
                'redirect_url' => $token->url,
                'pending_key' => $pendingKey,
                'message' => 'Redirection vers le paiement pour valider la modification',
                'reservation' => $reservation
            ], 200);
        }
        
        Cache::forget($pendingKey);
        return response()->json(['error' => 'link_generation_failed'], 500);
    }

    /**
     * Annule une réservation.
     */
    public function destroy(Reservation $reservation)
    {
        $this->authorize('delete', $reservation);

        DB::transaction(function () use ($reservation) {
            if ($reservation->chambre) {
                $reservation->chambre->update(['disponibilite' => 1]);
            }

            $reservation->status = Reservation::STATUS_CANCELLED ?? 'cancelled';
            $reservation->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Réservation annulée avec succès'
        ]);
    }

    /**
     * Liste les réservations du user connecté.
     */
    public function myReservations(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (! $user->hasPermissionTo('reservation-list-own')) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $perPage = max(1, intval($request->input('per_page', 10)));
        $query = Reservation::with('chambre')->where('user_id', $user->id)->orderBy('date_arrive', 'desc');

        return response()->json($query->paginate($perPage));
    }

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

        // Vérifier si c'est une modification ou une création
        $cached = Cache::get($pendingKey);
        $isUpdate = $cached && isset($cached['is_update']) && $cached['is_update'] === true;
        
        $reservation = Reservation::where('pending_key', $pendingKey)->first();
        if (! $reservation && $cached && isset($cached['reservation_id'])) {
            $reservation = Reservation::find($cached['reservation_id']);
        }
        
        if (! $reservation) {
            Log::warning('Pending reservation not found (db+cache)', ['pending_key' => $pendingKey]);
            return response()->json(['error' => 'reservation_not_found'], 404);
        }

        $transactionId = $reservation->transaction_id ?? ($payloadData['data']['id'] ?? $payloadData['id'] ?? null);
        if (! $transactionId) {
            Log::error('No transaction id', ['reservation_id' => $reservation->id, 'payload' => $payloadData]);
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
                DB::transaction(function () use ($reservation, $isUpdate, $cached) {
                    $res = Reservation::lockForUpdate()->find($reservation->id);
                    
                    if ($isUpdate && $cached) {
                        // C'est une modification : mettre à jour les dates et garder le statut
                        $res->date_arrive = $cached['date_arrive'];
                        $res->date_depart = $cached['date_depart'];
                        $res->dure_reservation = $cached['dure_reservation'];
                        // Garder le statut existant (paid, confirmed, etc.)
                    } else {
                        // C'est une création : mettre le statut à paid
                        $res->status = Reservation::STATUS_PAID;
                        if (!$res->numero_reservation || str_starts_with($res->numero_reservation, 'TEMP-')) {
                            $res->numero_reservation = 'RES-' . strtoupper(uniqid());
                        }
                    }
                    $res->save();
                    
                    $ch = Chambre::findOrFail($res->chambre_id);
                    $ch->disponibilite = 0;
                    $ch->save();
                });

                // Recharger la réservation avec les relations
                $reservation->refresh()->load('user');

                // Planifier la libération de la chambre après la date de départ (le jour suivant la date de départ)
                $dateDepart = Carbon::parse($reservation->date_depart);
                if ($dateDepart->isFuture()) {
                    $releaseDate = $dateDepart->copy()->addDay()->startOfDay();
                    ReleaseRoomAfterCheckout::dispatch($reservation)->delay($releaseDate);
                }

                // Notifier le client que sa réservation est confirmée
                NotificationHelper::notifyClientOfReservationConfirmation($reservation);

                Cache::forget($pendingKey);
                Log::info('Reservation paid after payment', ['reservation_id' => $reservation->id, 'tx' => $transactionId]);
                return response()->json(['ok' => true], 200);
            } catch (\Throwable $e) {
                Log::error('Failed to confirm reservation', ['err' => $e->getMessage()]);
                return response()->json(['error' => 'failed_to_confirm'], 500);
            }
        }

        if (in_array($status, ['declined', 'failed', 'canceled', 'rejected'])) {
            try {
                DB::transaction(function () use ($reservation) {
                    $res = Reservation::lockForUpdate()->find($reservation->id);
                    $res->status = Reservation::STATUS_CANCELLED ?? 'cancelled';
                    $res->save();

                    $ch = Chambre::findOrFail($res->chambre_id);
                    $ch->disponibilite = 1;
                    $ch->save();
                });

                Cache::forget($pendingKey);
                Log::info('Reservation cancelled after failed payment', ['reservation_id' => $reservation->id, 'tx' => $transactionId]);
                return response()->json(['ok' => true], 200);
            } catch (\Throwable $e) {
                Log::error('Failed to cancel reservation', ['err' => $e->getMessage()]);
                return response()->json(['error' => 'failed_to_cancel'], 500);
            }
        }

        return response()->json(['ok' => true, 'message' => 'no_action_for_status', 'status' => $status], 200);
    }

    public function pendingStatus(Request $request)
    {
        $request->validate(['pending_key' => 'required|string']);
        $reservation = Reservation::where('pending_key', $request->pending_key)->first();
        if ($reservation) {
            return response()->json(['found' => true, 'reservation' => $reservation]);
        }
        $pending = Cache::get($request->pending_key);
        if (! $pending) {
            return response()->json(['found' => false], 404);
        }
        return response()->json(['found' => true, 'pending' => $pending]);
    }

    public function handleFedaPayRedirect(Request $request)
    {
        $pendingKey = $request->query('pending_key');
        $status = $request->query('status');
        $transactionId = $request->query('id') ?? $request->query('transaction_id');

        Log::info('FedaPay redirect (client)', ['pendingKey' => $pendingKey, 'status' => $status, 'transactionId' => $transactionId]);

        // Si on a un pending_key, traiter l'annulation et libérer la chambre
        if ($pendingKey) {
            $reservation = Reservation::where('pending_key', $pendingKey)->first();
            
            if ($reservation) {
                // Vérifier le statut de la transaction si on a un transactionId
                $paymentStatus = strtolower($status ?? 'unknown');
                
                // Si le paiement est annulé ou a échoué, libérer la chambre
                // Ne pas annuler si le statut est "pending" car le webhook gérera ça
                if (in_array($paymentStatus, ['canceled', 'cancelled', 'declined', 'failed', 'rejected'])) {
                    try {
                        DB::transaction(function () use ($reservation) {
                            $res = Reservation::lockForUpdate()->find($reservation->id);
                            // Ne libérer que si la réservation n'est pas déjà payée ou confirmée
                            if (!in_array($res->status, [Reservation::STATUS_PAID, Reservation::STATUS_CONFIRMED, Reservation::STATUS_CHECKED_IN])) {
                                $res->status = Reservation::STATUS_CANCELLED;
                                $res->save();

                                $ch = Chambre::findOrFail($res->chambre_id);
                                $ch->disponibilite = 1;
                                $ch->save();
                            }
                        });
                        Log::info('Reservation cancelled and room freed after payment cancellation', [
                            'reservation_id' => $reservation->id,
                            'pending_key' => $pendingKey
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to cancel reservation after redirect', [
                            'err' => $e->getMessage(),
                            'reservation_id' => $reservation->id
                        ]);
                    }
                }
            }
        }

        $frontendBase = config('app.frontend_url');
        $redirectTo = $frontendBase . "/payment-result?pending_key=" . urlencode($pendingKey ?? '') . "&status=" . urlencode($status ?? '') . "&transaction_id=" . urlencode($transactionId ?? '');

        return redirect()->away($redirectTo);
    }
}
